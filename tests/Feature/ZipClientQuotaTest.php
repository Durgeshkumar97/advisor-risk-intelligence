<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessPortfolioFile;
use App\Models\Plan;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StockRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BrokerExportFixtures as Fixtures;
use Tests\TestCase;

/**
 * The monthly plan limit is a number of CLIENTS. A client folder in a ZIP is
 * one client however many broker files it holds.
 */
class ZipClientQuotaTest extends TestCase
{
    use RefreshDatabase;

    private const CSV_HEADER = "Scheme Name,ISIN,Units,Invested Value,Current Value\n";

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('portfolios');
        Mail::fake();

        $this->mock(StockRiskService::class)->shouldReceive('classifyBatch')->andReturn([]);

        $this->user = User::factory()->create();
    }

    private function csv(array $rows): string
    {
        return self::CSV_HEADER.implode("\n", array_map(fn ($row) => implode(',', $row), $rows))."\n";
    }

    private function processZip(array $entries): PortfolioFile
    {
        $zipPath = Storage::disk('portfolios')->path('uploads/clients.zip');
        @mkdir(dirname($zipPath), 0777, true);
        Fixtures::zip($zipPath, $entries);

        $parent = PortfolioFile::create([
            'user_id' => $this->user->id,
            'original_name' => 'clients.zip',
            'stored_name' => 'clients.zip',
            'path' => 'uploads/clients.zip',
            'mime_type' => 'application/zip',
            'file_size' => filesize($zipPath),
            'status' => PortfolioFile::STATUS_PENDING,
            'meta' => ['extension' => 'zip'],
        ]);

        ProcessPortfolioFile::dispatchSync($parent);

        return $parent->fresh();
    }

    private function clientNames(): array
    {
        return Portfolio::where('user_id', $this->user->id)->orderBy('id')->pluck('name')->all();
    }

    public function test_monthly_client_count_counts_a_client_folder_once(): void
    {
        $this->processZip([
            'Rajesh Kumar/a.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'Rajesh Kumar/b.csv' => $this->csv([['Example Liquid Fund', '', 5, 500, 520]]),
            'Rajesh Kumar/c.csv' => $this->csv([['Example Flexi Cap Fund', '', 5, 500, 530]]),
            'Priya Sharma/holdings.csv' => $this->csv([['Example Gilt Fund', '', 1, 100, 110]]),
            'amit_verma.csv' => "name,asset_type,current_value\nHDFC Bank,stock,20000\n",
        ]);

        $this->assertSame(5, PortfolioFile::where('user_id', $this->user->id)->where('meta->extension', '!=', 'zip')->count());
        $this->assertSame(3, PortfolioFile::monthlyClientCount($this->user->id));   // 3 clients, 5 files
    }

    public function test_the_upload_time_quota_check_counts_folders_not_files(): void
    {
        $plan = Plan::create([
            'name' => 'Starter', 'slug' => 'starter-'.uniqid(), 'price' => 999, 'duration_days' => 30,
            'is_active' => true, 'monthly_client_limit' => 2,
        ]);
        Subscription::create([
            'user_id' => $this->user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now(), 'ends_at' => now()->addDays(30), 'provider' => 'razorpay',
        ]);

        // 2 clients, 4 files: within a limit of 2 clients.
        $zipPath = tempnam(sys_get_temp_dir(), 'quota_').'.zip';
        Fixtures::zip($zipPath, [
            'Rajesh Kumar/a.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'Rajesh Kumar/b.csv' => $this->csv([['Example Liquid Fund', '', 5, 500, 520]]),
            'Rajesh Kumar/c.csv' => $this->csv([['Example Flexi Cap Fund', '', 5, 500, 530]]),
            'Priya Sharma/holdings.csv' => $this->csv([['Example Gilt Fund', '', 1, 100, 110]]),
        ]);

        $response = $this->actingAs($this->user)->post(route('portfolio.upload.store'), [
            'file' => new UploadedFile($zipPath, 'clients.zip', 'application/zip', null, true),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(['Rajesh Kumar', 'Priya Sharma'], $this->clientNames());

        @unlink($zipPath);
    }

    public function test_the_upload_time_quota_check_counts_the_clients_inside_an_outer_folder(): void
    {
        $plan = Plan::create([
            'name' => 'Starter', 'slug' => 'starter-'.uniqid(), 'price' => 999, 'duration_days' => 30,
            'is_active' => true, 'monthly_client_limit' => 2,
        ]);
        Subscription::create([
            'user_id' => $this->user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now(), 'ends_at' => now()->addDays(30), 'provider' => 'razorpay',
        ]);

        // One outer folder, three clients inside it: over a limit of 2. Counted
        // as the single folder "clients" this would be let through.
        $zipPath = tempnam(sys_get_temp_dir(), 'quota_').'.zip';
        Fixtures::zip($zipPath, [
            'clients/Rajesh Kumar/a.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'clients/Priya Sharma/a.csv' => $this->csv([['Example Liquid Fund', '', 5, 500, 520]]),
            'clients/Amit Verma/a.csv' => $this->csv([['Example Flexi Cap Fund', '', 5, 500, 530]]),
        ]);

        $response = $this->actingAs($this->user)->post(route('portfolio.upload.store'), [
            'file' => new UploadedFile($zipPath, 'clients.zip', 'application/zip', null, true),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertStringContainsString('This ZIP has 3 client(s) but only 2 slot(s) remain.', session('errors')->first('file'));
        $this->assertSame([], $this->clientNames());

        @unlink($zipPath);
    }
}
