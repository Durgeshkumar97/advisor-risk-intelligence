<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\User;
use App\Services\StockRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\BrokerExportFixtures as Fixtures;
use Tests\TestCase;

require_once __DIR__.'/../Support/SharedFixtures.php';

/**
 * A single uploaded file must say whose it is: an existing portfolio, or a
 * new client name. The form used to pre-select "Default Portfolio", an empty
 * value nothing filled in, so the file was accepted, reported as uploaded,
 * and then failed in the queue with a database error. A ZIP names its own
 * clients and needs neither.
 */
class UploadPortfolioChoiceTest extends TestCase
{
    use RefreshDatabase;

    private const SUCCESS = 'Portfolio uploaded successfully. Processing has started.';

    private const ZIP_NOTE = 'The selected portfolio was not used: a ZIP creates one portfolio per client folder.';

    private const ZIP_NAME_NOTE = 'The client name was not used: a ZIP names its clients from its folders.';

    private User $user;

    private Portfolio $existing;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('portfolios');
        Mail::fake();

        $this->mock(StockRiskService::class)->shouldReceive('classifyBatch')->andReturn([]);

        $this->user = activeSubscriberUser();
        $this->existing = Portfolio::create(['user_id' => $this->user->id, 'name' => 'My Portfolio']);
    }

    private function csv(string $name = 'holdings.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "Scheme Name,ISIN,Units,Invested Value,Current Value\nExample Gilt Fund,,10,1000,1100\n");
    }

    private function zip(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'choice_').'.zip';
        Fixtures::zip($path, ['Asha Rao/a.csv' => "Scheme Name,ISIN,Units,Invested Value,Current Value\nExample Gilt Fund,,10,1000,1100\n"]);

        return new UploadedFile($path, 'clients.zip', 'application/zip', null, true);
    }

    private function upload(array $fields): TestResponse
    {
        return $this->actingAs($this->user)->post(route('portfolio.upload.store'), $fields);
    }

    private function portfolioNames(): array
    {
        return Portfolio::where('user_id', $this->user->id)->orderBy('id')->pluck('name')->all();
    }

    // ─── a single file must name its portfolio ───────────────────────────

    public function test_a_single_file_with_neither_a_portfolio_nor_a_client_name_is_refused_before_anything_is_stored(): void
    {
        Queue::fake();

        $response = $this->upload(['portfolio_id' => '', 'client_name' => '', 'file' => $this->csv()]);

        $response->assertSessionHasErrors(['portfolio_id' => 'Choose a portfolio or enter a new client name.']);
        $this->assertSame(0, PortfolioFile::count());
        Queue::assertNothingPushed();
        $this->assertSame(['My Portfolio'], $this->portfolioNames());
    }

    public function test_a_single_file_with_both_a_portfolio_and_a_client_name_is_refused(): void
    {
        Queue::fake();

        $response = $this->upload(['portfolio_id' => $this->existing->id, 'client_name' => 'Rajesh Kumar', 'file' => $this->csv()]);

        $response->assertSessionHasErrors(['client_name' => 'Choose a portfolio or enter a new client name, not both.']);
        $this->assertSame(0, PortfolioFile::count());
        Queue::assertNothingPushed();
        $this->assertSame(['My Portfolio'], $this->portfolioNames());
    }

    public function test_a_blank_client_name_counts_as_no_client_name(): void
    {
        $this->upload(['portfolio_id' => '', 'client_name' => '   ', 'file' => $this->csv()])
            ->assertSessionHasErrors(['portfolio_id' => 'Choose a portfolio or enter a new client name.']);

        // …and a blank name beside a chosen portfolio is not "both".
        $this->upload(['portfolio_id' => $this->existing->id, 'client_name' => '  ', 'file' => $this->csv()])
            ->assertSessionHasNoErrors();
    }

    // ─── an existing portfolio ───────────────────────────────────────────

    public function test_a_single_file_goes_into_the_portfolio_that_was_chosen(): void
    {
        $response = $this->upload(['portfolio_id' => $this->existing->id, 'file' => $this->csv()]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', self::SUCCESS);
        $this->assertSame(['My Portfolio'], $this->portfolioNames());
        $this->assertSame(PortfolioFile::STATUS_PROCESSED, PortfolioFile::sole()->status);
        $this->assertSame(1, PortfolioAsset::where('portfolio_id', $this->existing->id)->count());
    }

    // ─── a new client name ───────────────────────────────────────────────

    public function test_a_new_client_name_creates_a_portfolio_with_that_name_and_the_file_goes_into_it(): void
    {
        $response = $this->upload(['client_name' => "  Rajesh   Kumar \t", 'file' => $this->csv()]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', self::SUCCESS);
        $this->assertSame(['My Portfolio', 'Rajesh Kumar'], $this->portfolioNames());      // trimmed, inner spaces collapsed

        $created = Portfolio::where('name', 'Rajesh Kumar')->sole();
        $file = PortfolioFile::sole();

        $this->assertSame($created->id, $file->portfolio_id);
        $this->assertSame(PortfolioFile::STATUS_PROCESSED, $file->status);
        $this->assertSame(1, PortfolioAsset::where('portfolio_id', $created->id)->count());
    }

    public function test_a_typed_name_that_matches_an_existing_portfolio_is_added_to_it_and_the_message_says_so(): void
    {
        foreach (['my portfolio', '  MY   PORTFOLIO ', "My\tPortfolio"] as $typed) {
            $response = $this->upload(['client_name' => $typed, 'file' => $this->csv()]);

            $response->assertSessionHasNoErrors();
            $response->assertSessionHas('success', self::SUCCESS." Added to existing portfolio 'My Portfolio'.");
        }

        // Three uploads, still one portfolio, and every file is in it.
        $this->assertSame(['My Portfolio'], $this->portfolioNames());
        $this->assertSame([$this->existing->id], PortfolioFile::pluck('portfolio_id')->unique()->values()->all());
        $this->assertSame(3, PortfolioFile::count());
    }

    public function test_a_typed_name_never_matches_another_users_portfolio(): void
    {
        $other = User::factory()->create();
        Portfolio::create(['user_id' => $other->id, 'name' => 'Rajesh Kumar']);

        $response = $this->upload(['client_name' => 'Rajesh Kumar', 'file' => $this->csv()]);

        $response->assertSessionHas('success', self::SUCCESS);
        $this->assertSame(['My Portfolio', 'Rajesh Kumar'], $this->portfolioNames());
        $this->assertSame($this->user->id, PortfolioFile::sole()->portfolio->user_id);
    }

    public function test_when_several_portfolios_share_the_typed_name_the_most_recently_updated_one_is_used(): void
    {
        $older = Portfolio::create(['user_id' => $this->user->id, 'name' => 'Rajesh Kumar']);
        $newer = Portfolio::create(['user_id' => $this->user->id, 'name' => 'Rajesh Kumar']);
        $older->forceFill(['updated_at' => now()->subDays(3)])->saveQuietly();
        $newer->forceFill(['updated_at' => now()->subDay()])->saveQuietly();

        $this->upload(['client_name' => 'rajesh kumar', 'file' => $this->csv()])
            ->assertSessionHas('success', self::SUCCESS." Added to existing portfolio 'Rajesh Kumar'.");

        $this->assertSame($newer->id, PortfolioFile::sole()->portfolio_id);
        $this->assertSame(3, Portfolio::where('user_id', $this->user->id)->count());
    }

    // ─── a ZIP names its own clients ─────────────────────────────────────

    public function test_a_zip_needs_neither_a_portfolio_nor_a_client_name(): void
    {
        $response = $this->upload(['portfolio_id' => '', 'client_name' => '', 'file' => $this->zip()]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', self::SUCCESS);
        $this->assertSame(['My Portfolio', 'Asha Rao'], $this->portfolioNames());
    }

    public function test_a_zip_uploaded_with_a_portfolio_selected_says_the_selection_was_not_used(): void
    {
        $response = $this->upload(['portfolio_id' => $this->existing->id, 'file' => $this->zip()]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', self::SUCCESS.' '.self::ZIP_NOTE);
        $this->assertSame(0, PortfolioAsset::where('portfolio_id', $this->existing->id)->count());
        $this->assertSame(['My Portfolio', 'Asha Rao'], $this->portfolioNames());
    }

    public function test_a_zip_uploaded_with_a_client_name_typed_says_the_name_was_not_used(): void
    {
        $response = $this->upload(['client_name' => 'Rajesh Kumar', 'file' => $this->zip()]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', self::SUCCESS.' '.self::ZIP_NAME_NOTE);
        // The typed name made no portfolio; the ZIP's folder did.
        $this->assertSame(['My Portfolio', 'Asha Rao'], $this->portfolioNames());
    }

    public function test_a_zip_uploaded_with_both_a_portfolio_and_a_client_name_says_neither_was_used(): void
    {
        $response = $this->upload(['portfolio_id' => $this->existing->id, 'client_name' => 'Rajesh Kumar', 'file' => $this->zip()]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', self::SUCCESS.' '.self::ZIP_NOTE.' '.self::ZIP_NAME_NOTE);
        $this->assertSame(['My Portfolio', 'Asha Rao'], $this->portfolioNames());
    }

    // ─── the form ────────────────────────────────────────────────────────

    public function test_the_upload_form_has_no_default_portfolio_option_and_offers_a_client_name_field(): void
    {
        $page = $this->actingAs($this->user)->get(route('portfolio.upload'));

        $page->assertOk()
            ->assertDontSee('Default Portfolio')
            ->assertSee('name="client_name"', false)
            ->assertSee('My Portfolio')
            ->assertSee('For a single file, choose a portfolio or type a new client name.');
    }

    public function test_the_page_shows_the_validation_message_and_keeps_what_was_typed(): void
    {
        $this->upload(['portfolio_id' => $this->existing->id, 'client_name' => 'Rajesh Kumar', 'file' => $this->csv()]);

        $this->actingAs($this->user)->get(route('portfolio.upload'))
            ->assertSee('Choose a portfolio or enter a new client name, not both.')
            ->assertSee('value="Rajesh Kumar"', false);
    }

    // ─── a failed upload leaves no empty portfolio behind ────────────────

    public function test_a_new_client_portfolio_is_not_left_behind_when_the_upload_itself_fails(): void
    {
        $this->mock(\App\Services\PortfolioUploadService::class)
            ->shouldReceive('handleUpload')->andThrow(new \RuntimeException('disk full'));

        $response = $this->upload(['client_name' => 'Rajesh Kumar', 'file' => $this->csv()]);

        $response->assertSessionHasErrors(['file' => 'Upload failed. Please try again.']);
        $this->assertSame(['My Portfolio'], $this->portfolioNames());
    }
}
