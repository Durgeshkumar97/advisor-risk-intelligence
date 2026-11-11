<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(\Tests\TestCase::class, RefreshDatabase::class);

/*
| The pages on layouts/guest (register, verify-email, confirm-password) show
| the RiskSignal wordmark, as the rest of the site does, not the stock Laravel
| logo that came with the Breeze scaffold.
*/

// The Laravel logo's SVG viewBox and the first point of its path.
const LARAVEL_LOGO_MARKERS = ['viewBox="0 0 316 316"', 'M305.8 81.125'];

function assertRiskSignalBrand(\Illuminate\Testing\TestResponse $response): void
{
    $response->assertOk()
        ->assertSee('Risk<span', false)
        ->assertSee('>Signal</span>', false)
        ->assertSee('aria-label="RiskSignal home"', false);

    foreach (LARAVEL_LOGO_MARKERS as $marker) {
        $response->assertDontSee($marker, false);
    }
}

it('shows the RiskSignal wordmark on the register page', function () {
    assertRiskSignalBrand($this->get(route('register')));
});

it('shows the RiskSignal wordmark on the verify-email page', function () {
    $user = User::factory()->unverified()->create();

    assertRiskSignalBrand($this->actingAs($user)->get(route('verification.notice')));
});

it('shows the RiskSignal wordmark on the confirm-password page', function () {
    $user = User::factory()->create();

    assertRiskSignalBrand($this->actingAs($user)->get(route('password.confirm')));
});
