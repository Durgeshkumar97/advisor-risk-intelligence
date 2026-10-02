<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Exceptions;
use Tests\TestCase;

/**
 * A scheduled command that fails must be reported (and so reach Sentry).
 *
 * Laravel already reports a failing FOREGROUND scheduled command. A command
 * scheduled with runInBackground() gets its exit code later, in a separate
 * `schedule:finish` process, and the framework reports nothing there — which
 * is how market-risk:sync exited 1 every day without anyone being told.
 */
class ScheduledTaskFailureReportingTest extends TestCase
{
    /** The scheduled event for a command, as the scheduler itself knows it. */
    private function scheduled(string $command): Event
    {
        // Tasks defined in bootstrap/app.php are registered when the console
        // application starts, as they are under `artisan schedule:run`.
        app(Kernel::class)->all();

        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains((string) $event->command, $command));

        $this->assertNotNull($event, "{$command} is not scheduled.");

        return $event;
    }

    public function test_market_risk_sync_is_a_background_task(): void
    {
        // The premise of this file: if it stops being a background task the
        // framework reports its failures itself.
        $this->assertTrue($this->scheduled('market-risk:sync')->runInBackground);
    }

    public function test_a_background_scheduled_command_that_exits_non_zero_is_reported(): void
    {
        Exceptions::fake();

        // What the scheduler runs when the background process ends with exit code 1.
        $this->artisan('schedule:finish', ['id' => $this->scheduled('market-risk:sync')->mutexName(), 'code' => '1']);

        Exceptions::assertReported(fn (\RuntimeException $e) => str_contains($e->getMessage(), 'market-risk:sync')
            && str_contains($e->getMessage(), 'failed with exit code [1]'));
    }

    public function test_a_background_scheduled_command_that_succeeds_reports_nothing(): void
    {
        Exceptions::fake();

        $this->artisan('schedule:finish', ['id' => $this->scheduled('market-risk:sync')->mutexName(), 'code' => '0']);

        Exceptions::assertNothingReported();
    }

    public function test_the_queue_worker_the_other_background_task_is_covered_too(): void
    {
        Exceptions::fake();

        $this->artisan('schedule:finish', ['id' => $this->scheduled('queue:work')->mutexName(), 'code' => '137']);

        Exceptions::assertReported(fn (\RuntimeException $e) => str_contains($e->getMessage(), 'queue:work')
            && str_contains($e->getMessage(), 'failed with exit code [137]'));
    }
}
