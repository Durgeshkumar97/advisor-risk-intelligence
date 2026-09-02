<?php

namespace App\Http\Controllers;

use App\Actions\Payments\CreateRazorpayOrderAction;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | CREATE ORDER  (POST /payment/create)
    |--------------------------------------------------------------------------
    |
    | Validates the buyer's details, resolves the plan, and delegates to
    | CreateRazorpayOrderAction which creates the Razorpay order and persists
    | a local Payment record in 'pending' status.
    |
    | Returns the order_id + amount to the client so it can open the
    | Razorpay checkout modal.
    |
    */

    public function create(Request $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | 1. RATE LIMITING — prevent order-creation spam
        |--------------------------------------------------------------------------
        */

        $rateKey = 'payment-create:'.$request->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {

            Log::warning('Payment create: rate limit exceeded', [
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
        | 2. VALIDATION
        |--------------------------------------------------------------------------
        |
        | 'exists:plans,slug' already confirms the plan exists, but we still
        | scope the query to is_active=true below so a deactivated plan cannot
        | be used even if someone knows its slug.
        |
        */

        try {

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email:rfc,dns', 'max:255'],
                'phone' => ['required', 'string', 'min:7', 'max:20'],
                'plan' => ['required', 'string', 'exists:plans,slug'],
            ]);
        } catch (ValidationException $e) {

            return response()->json(
                ['success' => false, 'message' => 'Invalid request.', 'errors' => $e->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. RESOLVE ACTIVE PLAN
        |--------------------------------------------------------------------------
        */

        $plan = Plan::query()
            ->where('slug', $validated['plan'])
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            return response()->json(
                ['success' => false, 'message' => 'The selected plan is no longer available.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 4. CREATE RAZORPAY ORDER
        |--------------------------------------------------------------------------
        */

        try {

            $payment = app(CreateRazorpayOrderAction::class)
                ->execute($plan, $validated);

            return response()->json([
                'success' => true,
                'order_id' => $payment->order_id,
                'amount' => (int) round($payment->amount * 100),  // paise
                'currency' => 'INR',
                'key' => config('services.razorpay.key'),
            ]);
        } catch (\Throwable $e) {

            Log::error('Payment create: order creation failed', [
                'ip' => $request->ip(),
                'plan' => $validated['plan'],
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                // Do NOT log full trace in production — too verbose; use Sentry/Flare instead
            ]);

            return response()->json(
                ['success' => false, 'message' => 'Could not create payment order. Please try again.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT FAILED  (POST /payment/failure)
    |--------------------------------------------------------------------------
    |
    | Fired by Razorpay client-side JS when the modal closes with an error.
    | There is no server-side signature on failure events — this endpoint
    | only flips 'pending' payments to 'failed'; it cannot be used to tamper
    | with 'paid' or 'processing' records (status guard below).
    |
    | Throttled in web.php (throttle:15,1).
    |
    */

    public function failure(Request $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | 1. VALIDATION
        |--------------------------------------------------------------------------
        */

        try {

            $validated = $request->validate([
                'order_id' => ['required', 'string', 'max:255'],
            ]);
        } catch (ValidationException $e) {

            return response()->json(
                ['success' => false, 'message' => 'Invalid request.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. MARK PAYMENT FAILED (with row-level lock for safety)
        |--------------------------------------------------------------------------
        |
        | Lock the row so a racing webhook cannot simultaneously mark it 'paid'
        | while we're marking it 'failed'.  Only 'pending' payments are touched;
        | any other status is left untouched (idempotency + safety guard).
        |
        */

        try {

            DB::transaction(function () use ($validated) {

                $payment = Payment::query()
                    ->where('order_id', $validated['order_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $payment) {
                    // Unknown order_id — silently ignore; don't expose DB state
                    return;
                }

                if ($payment->status !== 'pending') {
                    // Already paid / processing / failed — do not overwrite
                    return;
                }

                $payment->update(['status' => 'failed']);
            });

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {

            Log::error('Payment failure: status update failed', [
                'order_id' => $validated['order_id'] ?? null,
                'ip' => $request->ip(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json(
                ['success' => false, 'message' => 'Could not record payment failure.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
