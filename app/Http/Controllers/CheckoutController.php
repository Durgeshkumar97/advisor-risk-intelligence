<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function show($plan)
    {
        $plans = [
            'starter' => 999,
            'pro'     => 2499,
            'team'    => 4999,
        ];

        if (!array_key_exists($plan, $plans)) {
            abort(404);
        }

        return view('checkout', [
            'plan'  => $plan,
            'price' => $plans[$plan],
        ]);
    }

    public function success()
    {
        return view('payment-success');
    }
}