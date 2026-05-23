<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentSuccessNotification;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | HANDLE RAZORPAY WEBHOOK
    |--------------------------------------------------------------------------
    |
    | Razorpay fires this as a server-to-server backup when the browser
    | closes before the JS verify call completes.
    |
    | Safety guarantees:
    |   1. HMAC signature verified before any processing.
    |   2. Only payment.captured events acted on.
    |   3. payment->processed_at used as idempotency lock (lockForUpdate).
    |   4. User + subscription creation are themselves idempotent.
    |
    */

    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        try {

            /*
            |------------------------------------------------------------------
            | VERIFY WEBHOOK SIGNATURE
            |------------------------------------------------------------------
            */

            $payload   = $request->getContent();
            $signature = $request->header('X-Razorpay-Signature');
            $secret    = config('services.razorpay.webhook_secret');

            $expected = hash_hmac('sha256', $payload, $secret);

            if (!hash_equals($expected, (string) $signature)) {

                Log::warning('Razorpay webhook: invalid signature.', [
                    'ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'invalid_signature'], 400);
            }

            /*
            |------------------------------------------------------------------
            | ONLY HANDLE payment.captured
            |------------------------------------------------------------------
            */

            if ($request->input('event') !== 'payment.captured') {

                return response()->json(['status' => 'ignored']);
            }

            /*
            |------------------------------------------------------------------
            | EXTRACT ENTITY
            |------------------------------------------------------------------
            */

            $entity = $request->input('payload.payment.entity');

            if (!$entity || empty($entity['order_id'])) {

                Log::error('Razorpay webhook: missing payment entity.');

                return response()->json(['error' => 'missing_entity'], 422);
            }

            /*
            |------------------------------------------------------------------
            | PROCESS INSIDE TRANSACTION
            |------------------------------------------------------------------
            */

            DB::transaction(function () use ($entity) {

                /*
                |--------------------------------------------------------------
                | LOCK PAYMENT ROW
                |--------------------------------------------------------------
                */

                $payment = Payment::query()
                    ->where('order_id', $entity['order_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$payment) {

                    Log::warning('Razorpay webhook: no payment record found.', [
                        'order_id' => $entity['order_id'],
                    ]);

                    return;
                }

                /*
                |--------------------------------------------------------------
                | IDEMPOTENCY — skip if already processed
                |--------------------------------------------------------------
                */

                if ($payment->processed_at) {

                    Log::info('Razorpay webhook: payment already processed, skipping.', [
                        'payment_id' => $payment->id,
                    ]);

                    return;
                }

                /*
                |--------------------------------------------------------------
                | FIND PLAN via plan_id (not the legacy string column)
                |--------------------------------------------------------------
                */

                $plan = Plan::find($payment->plan_id);

                if (!$plan) {

                    Log::error('Razorpay webhook: plan not found.', [
                        'plan_id'    => $payment->plan_id,
                        'payment_id' => $payment->id,
                    ]);

                    return;
                }

                /*
                |--------------------------------------------------------------
                | FIND OR CREATE USER
                |--------------------------------------------------------------
                */

                $isNewUser  = false;
                $loginToken = null;

                $user = User::where('email', $payment->email)->first();

                if (!$user) {

                    $isNewUser  = true;
                    $loginToken = Str::random(60);

                    $user = User::create([
                        'name'        => $payment->name ?? 'Advisor',
                        'email'       => $payment->email,
                        'password'    => Hash::make(Str::random(32)),
                        'login_token' => $loginToken,
                    ]);
                }

                /*
                |--------------------------------------------------------------
                | CREATE SUBSCRIPTION (idempotent)
                |--------------------------------------------------------------
                */

                $alreadyActive = Subscription::query()
                    ->where('user_id', $user->id)
                    ->where('plan_id', $plan->id)
                    ->where('status', 'active')
                    ->exists();

                if (!$alreadyActive) {

                    $durationDays = $plan->duration_days ?? 30;

                    Subscription::create([
                        'user_id'                   => $user->id,
                        'plan_id'                   => $plan->id,
                        'status'                    => 'active',
                        'starts_at'                 => now(),
                        'ends_at'                   => now()->addDays($durationDays),
                        'renewal_at'                => now()->addDays($durationDays),
                        'provider'                  => 'razorpay',
                        'provider_subscription_id'  => $entity['id'],
                    ]);
                }

                /*
                |--------------------------------------------------------------
                | MARK PAYMENT PAID + PROCESSED
                |--------------------------------------------------------------
                */

                $payment->update([
                    'payment_id'   => $entity['id'],
                    'user_id'      => $user->id,
                    'status'       => Payment::STATUS_PAID,
                    'paid_at'      => now(),
                    'processed_at' => now(),
                ]);

                /*
                |--------------------------------------------------------------
                | NOTIFY (queued) — only for new users (existing get notif
                | from the browser-flow CompletePaymentAction)
                |--------------------------------------------------------------
                */

                if ($isNewUser) {
                    $user->notify(new PaymentSuccessNotification($payment));
                }

                Log::info('Razorpay webhook: payment processed successfully.', [
                    'payment_id' => $payment->id,
                    'user_id'    => $user->id,
                    'plan_id'    => $plan->id,
                    'new_user'   => $isNewUser,
                ]);
            });

            return response()->json(['status' => 'ok']);

        } catch (\Throwable $e) {

            Log::error('Razorpay webhook: unhandled exception.', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'server_error'], 500);
        }
    }
}
