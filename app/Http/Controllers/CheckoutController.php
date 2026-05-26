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
    | Guests always see the form — their account is created on payment
    | completion.  Logged-in users with an active/trial subscription are
    | redirected to the "already subscribed" view instead.
    |
    */

    public function show(string $slug)
    {
        $plan = Plan::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        if (Auth::check()) {

            $existing = Subscription::query()
                ->where('user_id', Auth::id())
                ->whereIn('status', ['active', 'trial'])
                ->with('plan')           // eager-load to avoid N+1 in the view
                ->latest()
                ->first();

            if ($existing) {
                return view('checkout-already-subscribed', [
                    'plan'        => $plan,
                    'subscription' => $existing,
                    'currentPlan' => $existing->plan,
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
    | PAYMENT SUCCESS  (POST /payment/success)
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
    | Route:  Route::post('/payment/success', [CheckoutController::class, 'success'])
    |             ->middleware('auth')   ← add if user is guaranteed logged in
    |             ->name('payment.success');
    |
    */

    public function success(Request $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | 1. RATE LIMITING — prevent brute-force order-id probing
        |--------------------------------------------------------------------------
        */

        $rateKey = 'checkout-success:' . $request->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 15)) {

            Log::warning('Checkout success: rate limit exceeded', [
                'ip' => $request->ip(),
            ]);

            return response()->json(
                ['success' => false, 'message' => 'Too many attempts. Please wait.'],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        RateLimiter::hit($rateKey, 60);

        /*
        |--------------------------------------------------------------------------
        | 2. INPUT VALIDATION
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | 3. RAZORPAY SIGNATURE VERIFICATION
        |--------------------------------------------------------------------------
        |
        | VerifyRazorpayPayment must use hash_equals() internally to
        | prevent timing attacks.  It should throw ValidationException on
        | failure and return the Payment model on success.
        |
        */

        try {

            $payment = app(VerifyRazorpayPayment::class)
                ->execute($validated);
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
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
            ]);

            return response()->json(
                ['success' => false, 'message' => 'Unable to process payment.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 4. LOCK ROW + IDEMPOTENCY + DISPATCH JOB
        |--------------------------------------------------------------------------
        |
        | The transaction acquires a row-level lock so concurrent requests for
        | the same payment (e.g. double-tap, duplicate POST) cannot both proceed.
        |
        | ProcessSuccessfulPayment is responsible for:
        |   - creating / linking the user account
        |   - provisioning the subscription
        |   - sending the welcome / receipt email
        |
        */

        try {

            DB::transaction(function () use ($payment) {

                /** @var Payment $locked */
                $locked = Payment::query()
                    ->whereKey($payment->id)
                    ->lockForUpdate()
                    ->firstOrFail();   // throws ModelNotFoundException if gone — rolls back

                /*
                 | Idempotency: processed_at is stamped by the job on completion.
                 | A second webhook / page reload must not dispatch a second job.
                 */
                if ($locked->processed_at !== null) {
                    return;
                }

                /*
                 | Guard against re-dispatching for a payment already in flight.
                 */
                if ($locked->status === 'processing') {
                    return;
                }

                $locked->update(['status' => Payment::STATUS_PROCESSING]);

                DB::afterCommit(fn() => ProcessSuccessfulPayment::dispatchSync(
                    $locked->fresh()
                ));
            });
        } catch (\Throwable $e) {

            Log::critical('Checkout success: transaction/dispatch failed', [
                'payment_id' => $payment->id,
                'order_id'   => $validated['razorpay_order_id'],
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message'    => $e->getMessage(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
            ]);

            return response()->json(
                ['success' => false, 'message' => 'Unable to process payment.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 5. LOG IN THE USER (guests only)
        |--------------------------------------------------------------------------
        |
        | The job runs asynchronously, so $payment->user may not exist yet if
        | the queue worker hasn't fired.  We refresh the model and attempt login
        | only when the user record is already available.
        |
        | If the job is synchronous (QUEUE_CONNECTION=sync) this always works.
        | For async queues, the client should poll or use a short-lived token.
        |
        */

        $payment->refresh();

        if (! Auth::check() && $payment->user) {

            Auth::login($payment->user);

            $request->session()->regenerate();

            // Non-critical — don't let a failed timestamp update break the flow
            rescue(fn() => $payment->user->forceFill([
                'last_login_at' => now(),
            ])->save());
        }

        /*
        |--------------------------------------------------------------------------
        | 6. RESOLVE REDIRECT TARGET
        |--------------------------------------------------------------------------
        */

        $user     = Auth::user() ?? $payment->user;
        $redirect = ($user && ! $user->onboarding_completed)
            ? route('onboarding')
            : route('dashboard');

        return response()->json([
            'success'  => true,
            'redirect' => $redirect,
        ]);
    }
}
