namespace App\Services;

use App\Models\Subscription;
use App\Models\Plan;

class BillingService
{
    public function activate($user, $planSlug)
    {
        $plan = Plan::where('slug', $planSlug)->firstOrFail();

        $existing = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$existing) {
            return Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
                'renewal_at' => now()->addMonth(),
                'provider' => 'razorpay'
            ]);
        }

        // Upgrade / extend
        $existing->update([
            'plan_id' => $plan->id,
            'ends_at' => now()->addMonth(),
            'renewal_at' => now()->addMonth()
        ]);

        return $existing;
    }
}