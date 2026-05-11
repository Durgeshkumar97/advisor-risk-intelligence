<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Jobs\ProcessSuccessfulPayment;

class ActivateSubscriptionListener
{
    public function handle(PaymentConfirmed $event): void
    {
        ProcessSuccessfulPayment::dispatch(
            $event->payment
        );
    }
}
