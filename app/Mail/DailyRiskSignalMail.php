<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DailyRiskSignalMail extends Mailable
{
    use SerializesModels;

    public $user;

    public $riskScore;

    public $riskLevel;

    public $nextAction;

    public function __construct($user, $riskScore, $riskLevel, $nextAction)
    {
        $this->user = $user;
        $this->riskScore = $riskScore;
        $this->riskLevel = $riskLevel;
        $this->nextAction = $nextAction;
    }

    public function build()
    {
        return $this
            ->subject('Your Daily Risk Signal')
            ->view('emails.daily-risk-signal');
    }
}
