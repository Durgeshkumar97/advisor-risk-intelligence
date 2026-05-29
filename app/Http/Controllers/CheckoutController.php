<?php

namespace App\Http\Controllers;

use App\Actions\Payments\VerifyRazorpayPayment;
use App\Jobs\ProcessSuccessfulPayment;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SHOW CHECKOUT PAGE
    |--------------------------------------------------------------------------
    |
    | Guards:
    |  1. Plan must be active.
    |  2. Authenticated users with an active or trial subscription are shown
    |     an "already subscribed" page instead of the checkout form.
    |     (Guests can always proceed — they will be registered on payment.)
    |
    */

    public function show(string $slug): \Illuminate\View\View
    {
        $plan = Plan::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        if (Auth::check()) {
            $existing = Subscription::where('user_id', Auth::id())
                ->whereIn('status', ['active', 'trial'])
                ->with('plan')
                ->latest()
                ->first();

            if ($existing) {
                return view('checkout-already-subscribed', [
                    'plan'         => $plan,
                    'subscription' => $existing,
                    'currentPlan'  => $existing->plan,
                ]);
            }
        }

        return view('checkout', [
            'plan'        => $plan,
            'razorpayKey' => config('services.razorpay.key'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT SUCCESS  (POST /payment/verify)
    |--------------------------------------------------------------------------
    |
    | Called by client-side Razorpay JS after the modal closes successfully.
    | We verify the HMAC signature, lock the payment row, mark it processing,
    | and dispatch a background job for the rest of the fulfilment work.
    |
    | Why POST and not GET?
    |   — Razorpay fields (payment_id, signature) must never appear in server
    |     logs, browser history, or Referer headers.
    |   — A GET endpoint returning JSON is a footgun: browsers navigate to it
    |     directly and the user just sees raw JSON.
    |
    */

    public function success(Request $request): JsonResponse
    {
        $rateKey = 'checkout-success:' . $request->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 15)) {
            Log::warning('Checkout success: rate limit exceeded', ['ip' => $request->ip()]);
            return response()->json(
                ['success' => false, 'message' => 'Too many attempts. Please wait.'],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        RateLimiter::hit($rateKey, 60);

        try {
            $validated = $request->validate([
                'razorpay_payment_id' => ['required', 'string', 'max:255'],
                'razorpay_order_id'   => ['required', 'string', 'max:255'],
                'razorpay_signature'  => ['required', 'string', 'max:500'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(
                ['success' => false, 'message' => 'Invalid request payload.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $payment = app(VerifyRazorpayPayment::class)->execute($validated);
        } catch (ValidationException $e) {
            Log::warning('Checkout success: signature verification failed', [
                'ip'       => $request->ip(),
                'order_id' => $validated['razorpay_order_id'],
                'message'  => $e->getMessage(),
            ]);
            return response()->json(
                ['success' => false, 'message' => 'Payment verification failed.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\Throwable $e) {
            Log::critical('Checkout success: verification threw unexpected error', [
                'ip'       => $request->ip(),
                'order_id' => $validated['razorpay_order_id'],
                'message'  => $e->getMessage(),
            ]);
            return response()->json(
                ['success' => false, 'message' => 'Unable to process payment.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        try {
            DB::transaction(function () use ($payment) {
                /** @var Payment $locked */
                $locked = Payment::query()
                    ->whereKey($payment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->processed_at !== null || $locked->status === 'processing') {
                    return;
                }

                $locked->update(['status' => Payment::STATUS_PROCESSING]);

                DB::afterCommit(fn() => ProcessSuccessfulPayment::dispatchSync($locked->fresh()));
            });
        } catch (\Throwable $e) {
            Log::critical('Checkout success: transaction/dispatch failed', [
                'payment_id' => $payment->id,
                'order_id'   => $validated['razorpay_order_id'],
                'ip'         => $request->ip(),
                'message'    => $e->getMessage(),
            ]);
            return response()->json(
                ['success' => false, 'message' => 'Unable to process payment.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $payment->refresh();

        if (!Auth::check() && $payment->user) {
            Auth::login($payment->user);
            $request->session()->regenerate();
            rescue(fn() => $payment->user->forceFill(['last_login_at' => now()])->save());
        }

        $user     = Auth::user() ?? $payment->user;
        $redirect = ($user && !$user->onboarding_completed)
            ? route('onboarding')
            : route('dashboard');

        return response()->json([
            'success'  => true,
            'redirect' => $redirect,
        ]);
    }
}
