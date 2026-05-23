<?php

namespace App\Http\Controllers;

use App\Models\Plan;

use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SHOW CHECKOUT PAGE
    |--------------------------------------------------------------------------
    */

    public function show(string $slug): \Illuminate\View\View
    {
        $plan = Plan::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return view('checkout', [
            'plan'        => $plan,
            'razorpayKey' => config('services.razorpay.key'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT SUCCESS PAGE
    |--------------------------------------------------------------------------
    |
    | The JS verify handler (PaymentController::verify) redirects here after
    | a successful payment.  All actual processing (user creation, subscription
    | activation) is already done by CompletePaymentAction at verify time, so
    | this action just renders the confirmation view.
    |
    */

    public function success(Request $request): \Illuminate\View\View
    {
        return view('payment-success');
    }
}
