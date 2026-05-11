<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ActivateUserSubscription;
use App\Actions\Payments\VerifyRazorpayPayment;
use App\Jobs\ProcessSuccessfulPayment;
use App\Models\Payment;
use App\Models\Plan;
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
    */

    public function show(string $slug)
    {
        $plan = Plan::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return view('checkout', [
            'plan' => $plan,
            'razorpayKey' => config('services.razorpay.key'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT SUCCESS
    |--------------------------------------------------------------------------
    |
    | Production-grade implementation:
    |
    | - Signature verification
    | - Rate limiting
    | - Idempotency
    | - Queue-safe architecture
    | - Transaction-safe operations
    | - Structured logging
    | - Fraud protection basics
    | - Safe authentication flow
    |
    */

    public function success(Request $request)
    { 
        /*
        |--------------------------------------------------------------------------
        | RATE LIMITING
        |--------------------------------------------------------------------------
        */

        $rateKey = sprintf(
            'checkout-success:%s',
            $request->ip()
        );

        if (RateLimiter::tooManyAttempts($rateKey, 15)) {

            Log::warning('Checkout rate limit exceeded', [
                'ip' => $request->ip(),
            ]);

            abort(Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($rateKey, 60);

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'razorpay_payment_id' => [
                'required',
                'string',
                'max:255',
            ],

            'razorpay_order_id' => [
                'required',
                'string',
                'max:255',
            ],

            'razorpay_signature' => [
                'required',
                'string',
                'max:500',
            ],
        ]);

        try {

            /*
            |--------------------------------------------------------------------------
            | VERIFY PAYMENT
            |--------------------------------------------------------------------------
            */

            $payment = app(VerifyRazorpayPayment::class)
                ->execute($validated);

            /*
            |--------------------------------------------------------------------------
            | LOCK PAYMENT ROW
            |--------------------------------------------------------------------------
            */

            DB::transaction(function () use ($payment) {

                $lockedPayment = Payment::query()
                    ->whereKey($payment->id)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedPayment) {

                    throw ValidationException::withMessages([
                        'payment' => 'Payment record missing.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | IDEMPOTENCY PROTECTION
                |--------------------------------------------------------------------------
                */

                if ($lockedPayment->processed_at) {
                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | DISPATCH BACKGROUND JOB
                |--------------------------------------------------------------------------
                */

                CompletePaymentAction::dispatch(
                    $lockedPayment
                );

                /*
                |--------------------------------------------------------------------------
                | MARK PROCESSING
                |--------------------------------------------------------------------------
                */

                $lockedPayment->update([
                    'processed_at' => now(),
                ]);
            });

            /*
            |--------------------------------------------------------------------------
            | SAFE USER LOGIN
            |--------------------------------------------------------------------------
            */

            $payment->refresh();

            if ($payment->user) {

                Auth::login($payment->user);

                $request->session()->regenerate();

                $payment->user->update([
                    'last_login_at' => now(),
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | SUCCESS RESPONSE
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'redirect' => $payment->user &&
                    !$payment->user->onboarding_completed
                    ? route('onboarding')
                    : route('dashboard'),
            ]);
        } catch (ValidationException $e) {

            Log::warning('Payment validation failed', [
                'message' => $e->getMessage(),
                'ip' => $request->ip(),
                'order_id' => $request->input('razorpay_order_id'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed.',
            ], 422);
        } catch (\Throwable $e) {

            Log::critical('Checkout success failure', [

                'message' => $e->getMessage(),

                'file' => $e->getFile(),

                'line' => $e->getLine(),

                'ip' => $request->ip(),

                'user_agent' => $request->userAgent(),

                'order_id' => $request->input('razorpay_order_id'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to process payment.',
            ], 500);
        }
    }
}
