<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\ClientIntake;

class CheckoutController extends Controller
{
    public function show($plan)
    {
        $plans = [
            'starter' => 799,
            'pro' => 1499,
            'team' => 3499,
        ];

        if (!array_key_exists($plan, $plans)) {
            abort(404);
        }

        return view('checkout', [
            'plan' => $plan,
            'price' => $plans[$plan],
        ]);
    }

    public function process(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'phone' => 'required',
            'email' => 'nullable|email',
            'plan' => 'required',
        ]);

        ClientIntake::create([
            'submission_uuid' => Str::uuid(),
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'portfolio_value' => 'NA',
            'objective' => 'NA',
            'horizon' => 'NA',
            'archetype' => 'NA',
            'concern' => 'NA',
            'lead_score' => 'Paid',
            'ai_status' => 'active',
        ]);

        return redirect()->route('payment.success');
    }

    public function success()
    {
        return view('payment-success');
    }
}
 