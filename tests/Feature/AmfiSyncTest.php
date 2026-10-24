<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\AmfiSyncFailed;
use App\Models\AmfiSyncRun;
use App\Models\MfScheme;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmfiSyncTest extends TestCase
{
    use RefreshDatabase;

    private string $fixture;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/Fixtures/amfi/NAVAll.txt');
        $this->tmp = sys_get_temp_dir().'/amfi-test-'.uniqid().'.txt';
        config(['amfi.min_rows' => 5]);
        $this->travelTo(Carbon::parse('2026-10-24 06:30', 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        @unlink($this->tmp);
        parent::tearDown();
    }

    private function sync(?string $source = null): int
    {
        return $this->artisan('amfi:sync', ['--source' => $source ?? $this->fixture])->run();
    }

    private function writeTmp(string $contents): string
    {
        file_put_contents($this->tmp, $contents);

        return $this->tmp;
    }

    private function fixtureLines(): array
    {
        return preg_split('/\r\n|\n/', file_get_contents($this->fixture));
    }

    public function test_it_syncs_from_a_local_file_path(): void
    {
        $this->assertSame(0, $this->sync());

        $this->assertSame(48, MfScheme::count());
        $run = AmfiSyncRun::lastSuccessful();
        $this->assertSame(48, $run->rows_parsed);
        $this->assertSame('2026-10-23', $run->file_date->toDateString());
    }

    public function test_it_uses_the_configured_source_when_no_option_is_given(): void
    {
        config(['amfi.source' => $this->fixture]);

        $this->artisan('amfi:sync')->assertSuccessful();

        $this->assertSame(48, MfScheme::count());
    }

    public function test_it_downloads_from_a_url(): void
    {
        Http::fake(['*' => Http::response(file_get_contents($this->fixture))]);

        $this->sync('https://www.amfiindia.com/spages/NAVAll.txt');

        $this->assertSame(48, MfScheme::count());
        Http::assertSentCount(1);
    }

    public function test_a_garbage_file_keeps_previous_data_and_reports(): void
    {
        $this->sync();
        $before = MfScheme::orderBy('scheme_code')->get()->toArray();
        Exceptions::fake();

        $result = $this->sync($this->writeTmp("lorem ipsum\nrandom text without structure\n"));

        $this->assertSame(1, $result);
        $this->assertSame($before, MfScheme::orderBy('scheme_code')->get()->toArray());
        Exceptions::assertReported(AmfiSyncFailed::class);
        $this->assertSame(1, AmfiSyncRun::where('status', 'failed')->count());
        $this->assertNotNull(AmfiSyncRun::where('status', 'failed')->first()->error);
    }

    public function test_a_truncated_file_trips_the_row_drop_gate(): void
    {
        $this->sync();
        $before = MfScheme::orderBy('scheme_code')->get()->toArray();
        Exceptions::fake();

        $truncated = implode("\r\n", array_slice($this->fixtureLines(), 0, 26));
        $result = $this->sync($this->writeTmp($truncated));

        $this->assertSame(1, $result);
        $this->assertSame($before, MfScheme::orderBy('scheme_code')->get()->toArray());
        Exceptions::assertReported(fn (AmfiSyncFailed $e) => str_contains($e->getMessage(), 'last successful run'));
        $this->assertSame(48, AmfiSyncRun::lastSuccessful()->rows_parsed);
    }

    public function test_a_http_error_keeps_data_and_reports(): void
    {
        $this->sync();
        Exceptions::fake();
        Http::fake(['*' => Http::response('boom', 500)]);

        $result = $this->sync('https://www.amfiindia.com/spages/NAVAll.txt');

        $this->assertSame(1, $result);
        $this->assertSame(48, MfScheme::count());
        Exceptions::assertReported(fn (AmfiSyncFailed $e) => str_contains($e->getMessage(), 'HTTP 500'));
    }

    public function test_a_stale_file_writes_nothing(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 06:30', 'Asia/Kolkata'));
        Exceptions::fake();

        $this->assertSame(1, $this->sync());

        $this->assertSame(0, MfScheme::count());
        Exceptions::assertReported(fn (AmfiSyncFailed $e) => str_contains($e->getMessage(), 'stale'));
    }

    public function test_a_rerun_is_idempotent_and_updates_changed_navs(): void
    {
        $this->sync();
        $this->sync();
        $this->assertSame(48, MfScheme::count());

        $changed = str_replace('28.6173', '30.0000', file_get_contents($this->fixture));
        $this->sync($this->writeTmp($changed));

        $this->assertSame(48, MfScheme::count());
        $this->assertSame(30.0, MfScheme::find('135762')->latest_nav);
    }

    public function test_a_scheme_missing_from_a_later_file_is_retained(): void
    {
        $this->sync();

        $without = collect($this->fixtureLines())
            ->reject(fn (string $line) => str_starts_with($line, '135762;'))
            ->implode("\r\n");
        $this->assertSame(0, $this->sync($this->writeTmp($without)));

        $this->assertSame(48, MfScheme::count());
        $this->assertNotNull(MfScheme::find('135762'));
    }

    public function test_it_is_scheduled_daily_at_0630(): void
    {
        app(Kernel::class)->all();

        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $e) => str_contains((string) $e->command, 'amfi:sync'));

        $this->assertNotNull($event);
        $this->assertSame('30 6 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(10, $event->expiresAt);
    }
}
