<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private const API_VERSION = 'v19.0';
    private const API_BASE = 'https://graph.facebook.com';

    private string $token;
    private string $phoneNumberId;
    private bool $testMode;

    public function __construct()
    {
        $this->token = config('services.whatsapp.token', '');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id', '');
        $this->testMode = (bool) config('services.whatsapp.test_mode', false);
    }

    public function sendRiskSignal(string $phone, string $userName, int $score, string $riskLevel, string $nextAction): bool
    {
        $dashboardUrl = url('/dashboard');

        return $this->sendTemplate(
            phone: $phone,
            templateName: 'daily_risk_signal',
            components: [[
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $userName],
                    ['type' => 'text', 'text' => (string) $score],
                    ['type' => 'text', 'text' => $riskLevel],
                    ['type' => 'text', 'text' => $nextAction],
                    ['type' => 'text', 'text' => $dashboardUrl],
                ],
            ]]
        );
    }

    public function sendWelcome(string $phone, string $userName, string $planName, string $dashboardUrl): bool
    {
        return $this->sendTemplate(
            phone: $phone,
            templateName: 'welcome_risksignal',
            components: [[
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $userName],
                    ['type' => 'text', 'text' => $planName],
                    ['type' => 'text', 'text' => $dashboardUrl],
                ],
            ]]
        );
    }

    public function sendTemplate(string $phone, string $templateName, array $components = [], string $languageCode = 'en_IN'): bool
    {
        $to = $this->formatPhone($phone);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
                'components' => $components,
            ],
        ];

        return $this->dispatch($payload, context: ['template' => $templateName, 'to' => $to]);
    }

    public function sendText(string $phone, string $body): bool
    {
        $to = $this->formatPhone($phone);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $body],
        ];

        return $this->dispatch($payload, context: ['type' => 'text', 'to' => $to]);
    }

    private function dispatch(array $payload, array $context = []): bool
    {
        if ($this->testMode) {
            Log::info('WhatsApp [TEST MODE]', array_merge($context, ['payload' => $payload]));
            return true;
        }

        if (empty($this->token) || empty($this->phoneNumberId)) {
            Log::warning('WhatsApp not configured', $context);
            return false;
        }

        $url = self::API_BASE . '/' . self::API_VERSION . '/' . $this->phoneNumberId . '/messages';

        try {
            $response = Http::withToken($this->token)->timeout(10)->post($url, $payload);

            if ($response->successful()) {
                Log::info('WhatsApp sent', array_merge($context, ['message_id' => $response->json('messages.0.id')]));
                return true;
            }

            Log::error('WhatsApp API error', array_merge($context, ['status' => $response->status(), 'body' => $response->json()]));
            return false;
        } catch (\Throwable $e) {
            Log::error('WhatsApp HTTP exception', array_merge($context, ['message' => $e->getMessage()]));
            return false;
        }
    }

    public function formatPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '91' . substr($digits, 1);
        }

        return $digits;
    }

    public function isConfigured(): bool
    {
        return !empty($this->token) && !empty($this->phoneNumberId);
    }
}
