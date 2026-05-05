<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Payment;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Plan;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {

            $payload = $request->getContent();
            $signature = $request->header('X-Razorpay-Signature');

            $expected = hash_hmac('sha256', $payload, env('RAZORPAY_WEBHOOK_SECRET'));

            if ($expected !== $signature) {
                Log::warning("Invalid webhook signature");
                return response()->json(['error' => 'invalid'], 400);
            }

            if ($request->event !== 'payment.captured') {
                return response()->json(['status' => 'ignored']);
            }

            $entity = $request->payload['payment']['entity'];

            DB::transaction(function () use ($entity) {

                $payment = Payment::where('order_id', $entity['order_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$payment || $payment->status === 'paid') {
                    return;
                }

                $user = User::firstOrCreate(
                    ['email' => $payment->email],
                    [
                        'password' => bcrypt(Str::random(12)),
                        'login_token' => Str::random(60)
                    ]
                );

                // regenerate token every payment
                $user->update([
                    'login_token' => Str::random(60)
                ]);

                $plan = Plan::where('slug', $payment->plan)->first();

                if (!$plan) {
                    Log::error("Plan not found");
                    return;
                }

                $payment->update([
                    'payment_id' => $entity['id'],
                    'user_id' => $user->id,
                    'status' => 'paid'
                ]);

                Subscription::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'plan_id' => $plan->id,
                        'status' => 'active',
                        'starts_at' => now(),
                        'ends_at' => now()->addDays($plan->duration_days ?? 30),
                        'renewal_at' => now()->addDays($plan->duration_days ?? 30),
                        'provider' => 'razorpay',
                        'provider_subscription_id' => $entity['id']
                    ]
                );
            });

            return response()->json(['status' => 'ok']);

        } catch (\Exception $e) {

            Log::error("Webhook error: " . $e->getMessage());

            return response()->json(['error' => 'server error'], 500);
        }
    }
}