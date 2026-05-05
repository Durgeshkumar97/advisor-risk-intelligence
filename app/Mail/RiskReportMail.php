<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class RiskReportMail extends Mailable
{
    public $user;
    public $score;
    public $level;

    public function __construct($user, $score, $level)
    {
        $this->user = $user;
        $this->score = $score;
        $this->level = $level;
    }

    public function build()
    {
        return $this->subject('Your Daily Risk Signal')
            ->view('emails.risk');
    }
}