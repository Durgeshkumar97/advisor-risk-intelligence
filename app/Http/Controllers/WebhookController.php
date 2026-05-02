namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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

                if (!$payment) {
                    Log::error("Payment not found");
                    return;
                }

                if ($payment->status === 'paid') {
                    return; // idempotent
                }

                $user = User::firstOrCreate(
                    ['email' => $payment->email],
                    ['password' => bcrypt(\Str::random(12))]
                );

                $plan = Plan::where('slug', $payment->plan)->first();

                if (!$plan) {
                    throw new \Exception("Plan not found");
                }

                // update payment
                $payment->update([
                    'payment_id' => $entity['id'],
                    'user_id' => $user->id,
                    'status' => 'paid'
                ]);

                // create/update subscription
                Subscription::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'plan_id' => $plan->id,
                        'status' => 'active',
                        'starts_at' => now(),
                        'ends_at' => now()->addDays($plan->duration_days),
                        'renewal_at' => now()->addDays($plan->duration_days),
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