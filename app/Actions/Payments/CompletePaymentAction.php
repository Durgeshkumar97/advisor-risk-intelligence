<?php

namespace App\Actions\Payments;

use App\Events\PaymentConfirmed;
use App\Models\Payment;

class CompletePaymentAction
{
    public function execute(Payment $payment): void
    {
        if ($payment->processed_at) {
            return;
        }

        event(new PaymentConfirmed($payment));
    }
}
