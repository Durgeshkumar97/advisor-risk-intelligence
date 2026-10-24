<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RiskAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

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
        return $this->subject('Your daily risk score: latest upload')
            ->view('emails.risk-alert');
    }
}
