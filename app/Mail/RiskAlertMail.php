<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RiskAlertMail extends Mailable
{
    use SerializesModels;

    public $user;
    public $score;
    public $level;
    public $action;

    public function __construct($user, $score, $level, $action)
    {
        $this->user = $user;
        $this->score = $score;
        $this->level = $level;
        $this->action = $action;
    }

    public function build()
    {
        return $this->subject("Your Daily Risk Signal")
            ->view('emails.risk-alert');
    }
}