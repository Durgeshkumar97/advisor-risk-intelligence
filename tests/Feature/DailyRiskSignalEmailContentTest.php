<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\DailyRiskSignalMail;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The daily email is descriptive only: score, band and a disclaimer. It must
 * carry no prescriptive language and no claims about current market
 * conditions — nothing in the system observes markets, and RiskSignal is not a
 * SEBI-registered Investment Adviser.
 */
class DailyRiskSignalEmailContentTest extends TestCase
{
    private const BANNED_TERMS = [
        'should',
        'consider',
        'recommend',
        'stay invested',
        'no action needed',
        'market',
        'this week',
    ];

    private function render(int $score, string $level): string
    {
        $user = User::factory()->make(['name' => 'Asha Rao']);

        // A prescriptive next-action string is passed on purpose: the email
        // must not render it anywhere.
        $mail = new DailyRiskSignalMail($user, $score, $level, 'You should consider rebalancing before the market moves.');

        return $mail->render();
    }

    public static function bands(): array
    {
        return [
            'low' => [18, 'LOW'],
            'medium' => [47, 'MEDIUM'],
            'high' => [82, 'HIGH'],
        ];
    }

    #[DataProvider('bands')]
    public function test_the_rendered_email_contains_no_banned_terms(int $score, string $level): void
    {
        $html = mb_strtolower($this->render($score, $level));

        foreach (self::BANNED_TERMS as $term) {
            $this->assertStringNotContainsString($term, $html, "Rendered {$level} email contains \"{$term}\".");
        }
    }

    #[DataProvider('bands')]
    public function test_the_rendered_email_still_delivers_score_band_and_disclaimer(int $score, string $level): void
    {
        $html = $this->render($score, $level);

        $this->assertStringContainsString((string) $score, $html);
        $this->assertStringContainsString($level, $html);
        $this->assertStringContainsString('It is not investment advice', $html);
        $this->assertStringContainsString('not registered as an Investment Adviser under SEBI regulations', $html);
        $this->assertStringContainsString('prices are not refreshed daily', $html);
    }

    public function test_the_template_source_contains_no_banned_terms(): void
    {
        $source = mb_strtolower(file_get_contents(resource_path('views/emails/daily-risk-signal.blade.php')));

        foreach (self::BANNED_TERMS as $term) {
            $this->assertStringNotContainsString($term, $source, "Template source contains \"{$term}\".");
        }
    }
}
