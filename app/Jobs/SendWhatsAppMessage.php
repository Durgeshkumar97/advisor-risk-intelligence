<?php

namespace App\Jobs;

use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 30;
    public int $maxExceptions = 2;

    public function __construct(
        public readonly string $type,
        public readonly string $phone,
        public readonly string $userName,
        public readonly int $score = 0,
        public readonly string $riskLevel = 'MEDIUM',
        public readonly string $nextAction = '',
        public readonly string $planName = '',
        public readonly string $dashboardUrl = '',
    ) {}

    public function handle(WhatsAppService $whatsapp): void
    {
        if (empty($this->phone)) {
            Log::info('SendWhatsAppMessage: skipped — no phone', ['type' => $this->type]);
            return;
        }

        $sent = match ($this->type) {
            'risk_signal' => $whatsapp->sendRiskSignal($this->phone, $this->userName, $this->score, $this->riskLevel, $this->nextAction),
            'welcome' => $whatsapp->sendWelcome($this->phone, $this->userName, $this->planName, $this->dashboardUrl),
            default => false,
        };

        if (!$sent) {
            throw new \RuntimeException("WhatsApp send failed. Type: {$this->type}, Phone: {$this->phone}");
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendWhatsAppMessage failed', ['type' => $this->type, 'phone' => $this->phone, 'message' => $e->getMessage()]);
    }
}
