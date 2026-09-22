<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The new-password policy (config/auth.php 'password_policy', applied via
 * Password::defaults() in AppServiceProvider) and the on-page hints that
 * describe it. Live breach lookups are disabled suite-wide in phpunit.xml;
 * tests that need them turn them on and fake the HIBP range API.
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $password, string $email = 'new-user@example.test')
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        return $this->post(route('register'), [
            'name' => 'New User',
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
            'cf-turnstile-response' => 'test-token',
        ]);
    }

    public function test_password_shorter_than_the_minimum_is_rejected(): void
    {
        $this->register('Quiet2Orbi!')->assertSessionHasErrors('password'); // 11 chars, all four types

        $this->assertDatabaseMissing('users', ['email' => 'new-user@example.test']);
    }

    public function test_password_at_the_minimum_length_is_accepted(): void
    {
        $this->register('Quiet2Orbit!')->assertSessionHasNoErrors(); // exactly 12

        $this->assertDatabaseHas('users', ['email' => 'new-user@example.test']);
    }

    public function test_minimum_length_follows_config(): void
    {
        config(['auth.password_policy.min' => 10]);

        $this->register('Quiet2Orb!')->assertSessionHasNoErrors(); // 10 chars
    }

    public function test_password_longer_than_64_characters_is_rejected(): void
    {
        $this->register('Aa1!'.str_repeat('x', 61))->assertSessionHasErrors('password'); // 65 chars, all four types
    }

    public function test_multibyte_password_over_bcrypt_72_byte_limit_is_rejected(): void
    {
        $password = 'Aa1!'.str_repeat('é', 36); // 40 characters, 76 bytes: would be silently truncated by bcrypt

        $this->register($password)->assertSessionHasErrors([
            'password' => 'The password is too long. Please use a shorter password.',
        ]);
    }

    public function test_accents_and_non_latin_letters_are_allowed_alongside_the_required_types(): void
    {
        $this->register('Mañana Café 2024!', 'latin@example.test')->assertSessionHasNoErrors();

        auth()->logout();

        $this->register('पासवर्ड Safe2024!', 'hindi@example.test')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'latin@example.test']);
        $this->assertDatabaseHas('users', ['email' => 'hindi@example.test']);
    }

    public function test_password_without_an_uppercase_letter_is_rejected(): void
    {
        $this->register('quiet2orbit!x')
            ->assertSessionHasErrors(['password' => __('validation.password.mixed', ['attribute' => 'password'])]);
    }

    public function test_password_without_a_lowercase_letter_is_rejected(): void
    {
        $this->register('QUIET2ORBIT!X')
            ->assertSessionHasErrors(['password' => __('validation.password.mixed', ['attribute' => 'password'])]);
    }

    public function test_password_without_a_number_is_rejected(): void
    {
        $this->register('QuietOrbit!xy')
            ->assertSessionHasErrors(['password' => __('validation.password.numbers', ['attribute' => 'password'])]);
    }

    public function test_password_without_a_special_character_is_rejected(): void
    {
        $this->register('QuietOrbit2xy')
            ->assertSessionHasErrors(['password' => __('validation.password.symbols', ['attribute' => 'password'])]);
    }

    public function test_password_with_all_four_character_types_is_accepted(): void
    {
        $this->register('QuietOrbit2x!')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'new-user@example.test']);
    }

    public function test_password_containing_the_users_email_is_rejected(): void
    {
        $this->register('Xx1!new-user@example.test')
            ->assertSessionHasErrors(['password' => 'The password must not contain your email address.']);

        $this->register('NEW-USER Forever1!')
            ->assertSessionHasErrors(['password' => 'The password must not contain your email address.']);
    }

    public function test_password_containing_the_service_name_is_rejected(): void
    {
        $this->register('My RiskSignal Pass1!')
            ->assertSessionHasErrors(['password' => 'The password must not contain the word "RiskSignal".']);
    }

    public function test_breached_password_is_rejected(): void
    {
        config(['auth.password_policy.check_breached' => true]);

        $password = 'Correct Horse Battery 1!';
        $hash = strtoupper(sha1($password));

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
            'api.pwnedpasswords.com/range/'.substr($hash, 0, 5) => Http::response(substr($hash, 5).":3861\r\n"),
        ]);

        $this->register($password)->assertSessionHasErrors('password');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.pwnedpasswords.com/range/'.substr($hash, 0, 5));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'pwnedpasswords') && str_contains($request->url(), substr($hash, 5)));
    }

    public function test_breach_check_fails_open_when_the_service_is_down(): void
    {
        config(['auth.password_policy.check_breached' => true]);

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
            'api.pwnedpasswords.com/*' => Http::response('', 503),
        ]);

        $this->register('Quiet Orbit Lantern 7!')->assertSessionHasNoErrors();
    }

    public function test_change_password_uses_the_same_policy(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old password value')]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'old password value',
                'password' => 'too-short',
                'password_confirmation' => 'too-short',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'old password value',
                'password' => 'Quiet Orbit Lantern 7!',
                'password_confirmation' => 'Quiet Orbit Lantern 7!',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Quiet Orbit Lantern 7!', $user->fresh()->password));
    }

    public function test_existing_short_passwords_can_still_log_in(): void
    {
        // The policy only applies when a password is chosen, never at sign-in.
        $user = User::factory()->create(['email' => 'legacy@example.test', 'password' => Hash::make('short123')]);

        $this->post(route('login'), ['email' => 'legacy@example.test', 'password' => 'short123']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_requirement_hints_render_under_new_password_fields_from_config(): void
    {
        config(['auth.password_policy.check_breached' => true]);

        $this->get(route('register'))
            ->assertOk()
            ->assertSeeInOrder([
                'Password requirements:',
                '12 to 64 characters',
                'At least one uppercase letter (A-Z)',
                'At least one lowercase letter (a-z)',
                'At least one number (0-9)',
                'At least one special character (e.g. ! @ # $ % &amp; *)',
                'Not a password exposed in a known data breach',
                'Must not contain your email or the word "RiskSignal"',
            ], false)
            ->assertDontSee('Tip:')
            ->assertSee('aria-describedby="password-requirements"', false)
            ->assertSee('minlength="12"', false)
            ->assertSee('Type the same password again to confirm it.');

        $this->get(route('password.reset', ['token' => 'any-token', 'email' => 'x@example.test']))
            ->assertOk()
            ->assertSee('12 to 64 characters')
            ->assertSee('At least one special character')
            ->assertDontSee('Min. 8 characters');

        config(['auth.password_policy.min' => 10, 'auth.password_policy.symbols' => false]);

        $this->get(route('register'))
            ->assertSee('10 to 64 characters')
            ->assertDontSee('At least one special character');
    }

    public function test_profile_page_shows_hints_under_all_four_password_fields(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('The password you use to sign in today.')
            ->assertSee('12 to 64 characters')
            ->assertSee('At least one uppercase letter (A-Z)')
            ->assertSee('Type the new password again to confirm it.')
            ->assertSee("Enter your current password to confirm it's you.", false)
            ->assertDontSee('Min. 8 characters');
    }

    public function test_sign_in_fields_show_the_rules_as_a_reminder(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Password requirements:')
            ->assertSee('At least one number (0-9)')
            ->assertSee('aria-describedby="password-hint"', false);

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Password requirements:');

        $this->actingAs(User::factory()->create())
            ->get(route('password.confirm'))
            ->assertOk()
            ->assertSee('Password requirements:');
    }
}
