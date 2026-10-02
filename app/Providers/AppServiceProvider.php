<?php

namespace App\Providers;

use Closure;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        // Breached-password lookup (Have I Been Pwned, k-anonymity: only the
        // first 5 chars of the SHA-1 hash leave the server). The framework
        // default waits 30s; cap it so a slow API can't stall signup. On
        // timeout/failure the check fails open and the password is accepted.
        $this->app->singleton(UncompromisedVerifier::class, function ($app) {
            return new NotPwnedVerifier($app[HttpFactory::class], 5);
        });

        // Exchange rates come from the operator's entries. A licensed feed
        // later is a different implementation of this one interface.
        $this->app->bind(\App\Contracts\FxRateProvider::class, \App\Services\Fx\DatabaseFxRateProvider::class);
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | DEFAULT STRING LENGTH
        |--------------------------------------------------------------------------
        */

        Schema::defaultStringLength(191);

        /*
        |--------------------------------------------------------------------------
        | FORCE HTTPS ONLY IN PRODUCTION
        |--------------------------------------------------------------------------
        */

        if ($this->app->environment('production')) {

            URL::forceScheme('https');
        }

        /*
        |--------------------------------------------------------------------------
        | REPORT FAILED BACKGROUND SCHEDULED COMMANDS
        |--------------------------------------------------------------------------
        |
        | Laravel reports a scheduled command that exits non-zero — unless it
        | was scheduled with runInBackground(). Then the exit code only arrives
        | later, in a separate `schedule:finish` process, and nothing is
        | reported: market-risk:sync exited 1 every day and nobody was told.
        |
        | report() goes to the exception handler and so to Sentry. A log line
        | would not: the log stack here is a file. The message matches the one
        | the framework uses for foreground commands.
        |
        | This only reports. It runs after the command has ended and changes
        | nothing about what the command did.
        |
        */

        Event::listen(function (ScheduledBackgroundTaskFinished $event): void {
            if ($event->task->exitCode !== 0) {
                report(new \RuntimeException(sprintf(
                    'Scheduled command [%s] failed with exit code [%s].',
                    $event->task->command,
                    $event->task->exitCode,
                )));
            }
        });

        /*
        |--------------------------------------------------------------------------
        | NEW-PASSWORD POLICY (see config/auth.php 'password_policy')
        |--------------------------------------------------------------------------
        */

        Password::defaults(function () {
            $policy = config('auth.password_policy');

            $rule = Password::min($policy['min'])
                ->max($policy['max'])
                ->rules([$this->passwordContextRule()]);

            $rule = $policy['mixed_case'] ? $rule->mixedCase() : $rule;
            $rule = $policy['numbers'] ? $rule->numbers() : $rule;
            $rule = $policy['symbols'] ? $rule->symbols() : $rule;

            return $policy['check_breached'] ? $rule->uncompromised() : $rule;
        });
    }

    /**
     * Rejects passwords bcrypt would silently truncate (> 72 bytes, reachable
     * under the 64-character cap with multi-byte text) and passwords built
     * from context-specific words: the user's email or the service name.
     */
    private function passwordContextRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $password = (string) $value;

            if (strlen($password) > 72) {
                $fail('The :attribute is too long. Please use a shorter password.');

                return;
            }

            $lower = mb_strtolower($password);
            $email = mb_strtolower(trim((string) (request()->input('email') ?: auth()->user()?->email)));
            $localPart = strstr($email, '@', true) ?: '';

            if (str_contains($lower, 'risksignal')) {
                $fail('The :attribute must not contain the word "RiskSignal".');
            } elseif ($email !== '' && (str_contains($lower, $email) || (mb_strlen($localPart) >= 4 && str_contains($lower, $localPart)))) {
                $fail('The :attribute must not contain your email address.');
            }
        };
    }
}
