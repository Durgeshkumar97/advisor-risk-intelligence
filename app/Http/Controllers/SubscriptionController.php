<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClientIntake;

class SubscriptionController extends Controller
{
    private array $planPrices = [
        'starter' => 799,
        'pro'     => 1499,
        'team'    => 3499,
    ];

    public function upgrade(Request $request)
    {
        // validate
        $validated = $request->validate([
            'email' => 'required|email',
            'plan'  => 'required|in:starter,pro,team',
        ]);

        // find user
        $user = ClientIntake::where('email', $validated['email'])->first();

        if (!$user) {
            return back()->with('error', 'User not found. Start trial first.');
        }

        // get price
        $price = $this->planPrices[$validated['plan']];

        // upgrade logic
        $user->update([
            'plan' => $validated['plan'],
            'status' => 'active',
            'converted_at' => now(),

            'plan_price' => $price,
            'revenue_generated' => $price,
        ]);

        return back()->with('success', 'Plan upgraded successfully.');
    }
}