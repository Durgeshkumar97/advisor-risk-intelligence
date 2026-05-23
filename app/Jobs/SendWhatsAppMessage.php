<?php

namespace App\Jobs;

use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SendWhatsAppMessage
 *
 * Queued job that delivers a single WhatsApp message via Meta Cloud API.
 * Self-contained — all data is passed at dispatch time, no DB re-query needed.
 *
 * DISPATCH EXAMPLES
 * ─────────────────
 *
 *   // Daily risk signal (template: daily_risk_signal)
 *   SendWhatsAppMessage::dispatch(
 *       type:       'risk_signal',
 *       phone:      $user->phone,
 *       userName:   $user->name,
 *       score:      42,
 *       riskLevel:  'MEDIUM',
 *       nextAction: 'Portfolio is within acceptable risk parameters.',
 *   );
 *
 *   // Welcome after payment (template: welcome_risksignal)
 *   SendWhatsAppMessage::dispatch(
 *       type:         'welcome',
 *       phone:        $user->phone,
 *       userName:     $user->name,
 *       planName:     'Professional',
 *       dashboardUrl: 'https://risksignal.in/dashboard',
 *   );
 */
class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | QUEUE SETTINGS
    |--------------------------------------------------------------------------
    */

    public int $tries        = 3;
    public int $backoff       = 30;   // seconds between retries
    public int $timeout       = 30;   // WhatsApp API is fast
    public int $maxExceptions = 2;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    |
    | All fields nullable — only the ones relevant to $type are used.
    |
    */

    public function __construct(
        public readonly string  $type,           // 'risk_signal' | 'welcome'
        public readonly string  $phone,
        public readonly string  $userName,

        // risk_signal fields
        public readonly int     $score      = 0,
        public readonly string  $riskLevel  = 'MEDIUM',
        public readonly string  $nextAction = '',

        // welcome fields
        public readonly string  $planName     = '',
        public readonly string  $dashboardUrl = '',
    ) {}

    /*
    |--------------------------------------------------------------------------
    | HANDLE
    |--------------------------------------------------------------------------
    */

    public function handle(WhatsAppService $whatsapp): void
    {
        if (empty($this->phone)) {
            Log::info('SendWhatsAppMessage: skipped — no phone number.', ['type' => $this->type]);
            return;
        }

        $sent = match ($this->type) {

            'risk_signal' => $whatsapp->sendRiskSignal(
                phone:      $this->phone,
                userName:   $this->userName,
                score:      $this->score,
                riskLevel:  $this->riskLevel,
                nextAction: $this->nextAction,
            ),

            'welcome' => $whatsapp->sendWelcome(
                phone:        $this->phone,
                userName:     $this->userName,
                planName:     $this->planName,
                dashboardUrl: $this->dashboardUrl,
            ),

            default => false,
        };

        if (!$sent) {
            // Throw so the queue retries (respects $tries and $backoff)
            throw new \RuntimeException(
                "WhatsApp send failed. Type: {$this->type}, Phone: {$this->phone}"
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FAILED
    |--------------------------------------------------------------------------
    |
    | Called after all retries exhausted. Logs the failure so it's visible
    | in the failed_jobs table (monitored by supervisor / alerting).
    |
    */

    public function failed(\Throwable $e): void
    {
        Log::error('SendWhatsAppMessage: permanently failed after all retries.', [
            'type'    => $this->type,
            'phone'   => $this->phone,
            'message' => $e->getMessage(),
        ]);
    }
}
