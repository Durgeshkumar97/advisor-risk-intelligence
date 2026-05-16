<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\User;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateUserFromPaymentAction
{
    public function execute(Payment $payment): User
    {
        /*
        |----------------------------------------------------------------------
        | EXISTING USER
        |----------------------------------------------------------------------
        */

        $existingUser = User::where(
            'email',
            $payment->email
        )->first();

        if ($existingUser) {

            if (!$payment->user_id) {

                $payment->update([
                    'user_id' => $existingUser->id,
                ]);
            }

            return $existingUser;
        }

        /*
        |----------------------------------------------------------------------
        | TEMP PASSWORD
        |----------------------------------------------------------------------
        */

        $temporaryPassword = Str::random(16);

        /*
        |----------------------------------------------------------------------
        | CREATE USER
        |----------------------------------------------------------------------
        */

        $user = User::create([

            'name' => $payment->name,

            'email' => $payment->email,

            'password' => Hash::make(
                $temporaryPassword
            ),
        ]);

        /*
        |----------------------------------------------------------------------
        | LINK PAYMENT
        |----------------------------------------------------------------------
        */

        $payment->update([
            'user_id' => $user->id,
        ]);

        /*
        |----------------------------------------------------------------------
        | SEND PASSWORD SETUP EMAIL
        |----------------------------------------------------------------------
        */

        return $user;
    }
}
