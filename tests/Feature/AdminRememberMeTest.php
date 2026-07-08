<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(\Tests\TestCase::class, RefreshDatabase::class);

function adminRememberMeTestAdmin(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'is_admin' => true,
        'password' => bcrypt('correct-password'),
    ], $overrides));
}

describe('admin login never issues a persistent "remember me" cookie', function () {

    it('queues no recaller cookie and never rotates remember_token, even when remember=1 is submitted', function () {
        // The factory seeds a random remember_token on every user by default
        // (stock Laravel scaffolding, unrelated to login) — so the proof
        // that "remember me" was never honoured isn't that the token is
        // null, it's that Auth::attempt(..., false) never touches it.
        $admin = adminRememberMeTestAdmin();
        $tokenBeforeLogin = $admin->remember_token;

        $response = $this->post(route('admin.login.submit'), [
            'email'    => $admin->email,
            'password' => 'correct-password',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('admin.dashboard'));

        // The recaller cookie name is derived from the guard driver, not
        // hardcoded — this is exactly the cookie SessionGuard::attempt()
        // would have queued had $remember been honoured.
        $recallerName = Auth::guard('web')->getRecallerName();

        $response->assertCookieMissing($recallerName);

        expect($admin->fresh()->remember_token)->toBe($tokenBeforeLogin);
    });

    it('behaves identically when remember is omitted entirely', function () {
        $admin = adminRememberMeTestAdmin();
        $tokenBeforeLogin = $admin->remember_token;

        $response = $this->post(route('admin.login.submit'), [
            'email'    => $admin->email,
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));

        $recallerName = Auth::guard('web')->getRecallerName();

        $response->assertCookieMissing($recallerName);

        expect($admin->fresh()->remember_token)->toBe($tokenBeforeLogin);
    });
});
