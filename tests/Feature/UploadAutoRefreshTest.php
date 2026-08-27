<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

require_once __DIR__.'/../Support/SharedFixtures.php';

/**
 * The upload page auto-refreshes only while work is actually in flight, so the
 * polling stops on its own instead of reloading a settled page forever.
 */
class UploadAutoRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('portfolios');
    }

    private function fileWithStatus(User $user, string $status): PortfolioFile
    {
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Client']);

        return PortfolioFile::create([
            'user_id' => $user->id,
            'portfolio_id' => $portfolio->id,
            'original_name' => 'p.csv',
            'stored_name' => 'p.csv',
            'path' => 'uploads/p.csv',
            'mime_type' => 'text/csv',
            'file_size' => 10,
            'status' => $status,
        ]);
    }

    public static function inFlightStatuses(): array
    {
        return [
            // Pending counts: the cron-driven worker means a just-uploaded file
            // sits pending for up to a minute before processing even begins.
            'pending' => [PortfolioFile::STATUS_PENDING],
            'processing' => [PortfolioFile::STATUS_PROCESSING],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('inFlightStatuses')]
    public function test_the_page_polls_while_a_file_is_in_flight(string $status): void
    {
        $user = activeSubscriberUser();
        $this->fileWithStatus($user, $status);

        $this->actingAs($user)
            ->get(route('portfolio.upload'))
            ->assertOk()
            ->assertSee('data-status="'.$status.'"', false)
            ->assertSee('this page refreshes automatically');
    }

    public static function settledStatuses(): array
    {
        return [
            'processed' => [PortfolioFile::STATUS_PROCESSED],
            'failed' => [PortfolioFile::STATUS_FAILED],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('settledStatuses')]
    public function test_the_page_stops_polling_once_every_file_has_settled(string $status): void
    {
        $user = activeSubscriberUser();
        $this->fileWithStatus($user, $status);

        $this->actingAs($user)
            ->get(route('portfolio.upload'))
            ->assertOk()
            ->assertDontSee('data-status=', false)
            ->assertDontSee('this page refreshes automatically');
    }

    public function test_one_in_flight_file_among_settled_ones_still_polls(): void
    {
        $user = activeSubscriberUser();
        $this->fileWithStatus($user, PortfolioFile::STATUS_PROCESSED);
        $this->fileWithStatus($user, PortfolioFile::STATUS_FAILED);
        $this->fileWithStatus($user, PortfolioFile::STATUS_PROCESSING);

        $this->actingAs($user)
            ->get(route('portfolio.upload'))
            ->assertOk()
            ->assertSee('data-status="processing"', false);
    }
}
