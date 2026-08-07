<?php

namespace App\Services\Notifications;

use App\Mail\RiskAlertMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sends the daily risk-alert signal to a user.
 *
 * Wraps RiskAlertMail so it can be dispatched via:
 *   $user->notify(new RiskAlertNotification($score, $level, $action));
 *
 * Channels:
 *   - mail  (always)
 *   - database  (so the user can see alerts in their dashboard)
 */
class RiskAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(
        public readonly float $score,
        public readonly string $level,
        public readonly string $action,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | CHANNELS
    |--------------------------------------------------------------------------
    */

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /*
    |--------------------------------------------------------------------------
    | MAIL CHANNEL
    |--------------------------------------------------------------------------
    */

    public function toMail(object $notifiable): RiskAlertMail
    {
        return new RiskAlertMail(
            user: $notifiable,
            score: $this->score,
            level: $this->level,
            action: $this->action,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DATABASE CHANNEL
    |--------------------------------------------------------------------------
    */

    public function toArray(object $notifiable): array
    {
        return [
            'score' => $this->score,
            'level' => $this->level,
            'action' => $this->action,
        ];
    }
}
