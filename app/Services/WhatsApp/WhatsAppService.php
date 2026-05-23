<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsAppService
 *
 * Wrapper around the Meta WhatsApp Cloud API (v19.0).
 *
 * SETUP REQUIRED
 * ──────────────
 *   1. Create a Meta Business Manager account at business.facebook.com
 *   2. Add a WhatsApp Business Account (WABA)
 *   3. Get a permanent System User token with whatsapp_business_messaging permission
 *   4. Get your Phone Number ID from Meta Developer Console
 *   5. Register and get templates approved (see DEPLOY.md)
 *
 * ENV VARS
 * ────────
 *   WHATSAPP_TOKEN          — permanent System User access token
 *   WHATSAPP_PHONE_NUMBER_ID — numeric phone number ID (not the phone number itself)
 *   WHATSAPP_TEST_MODE      — set to "true" to log messages instead of sending
 *
 * USAGE
 * ─────
 *   $wa = app(WhatsAppService::class);
 *
 *   // Send a pre-approved template message
 *   $wa->sendRiskSignal($user->phone, $user->name, 42, 'MEDIUM', 'Action text');
 *
 *   // Send a welcome message after subscription
 *   $wa->sendWelcome($user->phone, $user->name, 'Professional', $dashboardUrl);
 *
 *   // Raw template (custom use)
 *   $wa->sendTemplate('9876543210', 'template_name', [
 *       ['type' => 'text', 'text' => 'Hello'],
 *   ]);
 */
class WhatsAppService
{
    private const API_VERSION = 'v19.0';
    private const API_BASE    = 'https://graph.facebook.com';

    private string $token;
    private string $phoneNumberId;
    private bool   $testMode;

    public function __construct()
    {
        $this->token         = config('services.whatsapp.token', '');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id', '');
        $this->testMode      = (bool) config('services.whatsapp.test_mode', false);
    }

    /*
    |--------------------------------------------------------------------------
    | HIGH-LEVEL HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Send the daily risk signal WhatsApp message.
     *
     * Template name: daily_risk_signal
     * Body params:
     *   {{1}} user name
     *   {{2}} risk score (0–100)
     *   {{3}} risk level (LOW / MEDIUM / HIGH)
     *   {{4}} next action text
     *   {{5}} dashboard URL
     */
    public function sendRiskSignal(
        string $phone,
        string $userName,
        int    $score,
        string $riskLevel,
        string $nextAction
    ): bool {
        $dashboardUrl = url('/dashboard');

        return $this->sendTemplate(
            phone:        $phone,
            templateName: 'daily_risk_signal',
            components:   [
                [
                    'type'       => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $userName],
                        ['type' => 'text', 'text' => (string) $score],
                        ['type' => 'text', 'text' => $riskLevel],
                        ['type' => 'text', 'text' => $nextAction],
                        ['type' => 'text', 'text' => $dashboardUrl],
                    ],
                ],
            ]
        );
    }

    /**
     * Send the welcome WhatsApp message after a new subscription.
     *
     * Template name: welcome_risksignal
     * Body params:
     *   {{1}} user name
     *   {{2}} plan name
     *   {{3}} dashboard URL
     */
    public function sendWelcome(
        string $phone,
        string $userName,
        string $planName,
        string $dashboardUrl
    ): bool {
        return $this->sendTemplate(
            phone:        $phone,
            templateName: 'welcome_risksignal',
            components:   [
                [
                    'type'       => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $userName],
                        ['type' => 'text', 'text' => $planName],
                        ['type' => 'text', 'text' => $dashboardUrl],
                    ],
                ],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CORE API METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Send a pre-approved WhatsApp template message.
     *
     * @param  string  $phone       Raw phone number (any format — will be normalised)
     * @param  string  $templateName  Registered template name in Meta Business Manager
     * @param  array   $components  Template components (body params, header, buttons…)
     * @param  string  $languageCode  Template language code (default: en_IN)
     */
    public function sendTemplate(
        string $phone,
        string $templateName,
        array  $components   = [],
        string $languageCode = 'en_IN'
    ): bool {
        $to = $this->formatPhone($phone);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'     => $templateName,
                'language' => ['code' => $languageCode],
                'components' => $components,
            ],
        ];

        return $this->dispatch($payload, context: [
            'template' => $templateName,
            'to'       => $to,
        ]);
    }

    /**
     * Send a plain text WhatsApp message.
     *
     * NOTE: Only works within 24 hours of the user messaging you first
     *       (session window). For business-initiated notifications, always
     *       use sendTemplate() with an approved template.
     *
     * Use this method in WHATSAPP_TEST_MODE for development.
     */
    public function sendText(string $phone, string $body): bool
    {
        $to = $this->formatPhone($phone);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => $body,
            ],
        ];

        return $this->dispatch($payload, context: [
            'type' => 'text',
            'to'   => $to,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL DISPATCH
    |--------------------------------------------------------------------------
    */

    private function dispatch(array $payload, array $context = []): bool
    {
        /*
        |----------------------------------------------------------------------
        | TEST MODE — log instead of sending (dev / staging)
        |----------------------------------------------------------------------
        */

        if ($this->testMode) {
            Log::info('WhatsApp [TEST MODE] — message would be sent.', array_merge($context, [
                'payload' => $payload,
            ]));
            return true;
        }

        /*
        |----------------------------------------------------------------------
        | GUARD — token and phone number ID must be configured
        |----------------------------------------------------------------------
        */

        if (empty($this->token) || empty($this->phoneNumberId)) {
            Log::warning('WhatsApp not configured — WHATSAPP_TOKEN or WHATSAPP_PHONE_NUMBER_ID missing.', $context);
            return false;
        }

        /*
        |----------------------------------------------------------------------
        | SEND
        |----------------------------------------------------------------------
        */

        $url = self::API_BASE . '/' . self::API_VERSION . '/' . $this->phoneNumberId . '/messages';

        try {
            $response = Http::withToken($this->token)
                ->timeout(10)
                ->post($url, $payload);

            if ($response->successful()) {
                Log::info('WhatsApp sent.', array_merge($context, [
                    'message_id' => $response->json('messages.0.id'),
                ]));
                return true;
            }

            Log::error('WhatsApp API error.', array_merge($context, [
                'status'   => $response->status(),
                'body'     => $response->json(),
            ]));
            return false;

        } catch (\Throwable $e) {
            Log::error('WhatsApp HTTP exception.', array_merge($context, [
                'message' => $e->getMessage(),
            ]));
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PHONE NUMBER NORMALISATION
    |--------------------------------------------------------------------------
    |
    | Converts any Indian/international phone number to the format
    | WhatsApp Cloud API expects: all digits, no +, no spaces.
    |
    | Examples:
    |   "9876543210"      → "919876543210"   (10-digit India)
    |   "+91 98765 43210" → "919876543210"   (formatted India)
    |   "919876543210"    → "919876543210"   (already correct)
    |   "919876543210"    → "919876543210"   (12-digit India)
    |
    */

    public function formatPhone(string $phone): string
    {
        // Strip everything except digits
        $digits = preg_replace('/\D/', '', $phone);

        // 10-digit Indian number — prepend country code
        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        // 11-digit starting with 0 (STD format) — replace leading 0 with 91
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '91' . substr($digits, 1);
        }

        // Already has country code (12 digits starting with 91)
        return $digits;
    }

    /*
    |--------------------------------------------------------------------------
    | SERVICE HEALTH CHECK
    |--------------------------------------------------------------------------
    */

    public function isConfigured(): bool
    {
        return !empty($this->token) && !empty($this->phoneNumberId);
    }
}
