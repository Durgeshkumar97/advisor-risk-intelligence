<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

require_once __DIR__.'/../Support/SharedFixtures.php';

/** The upload page tells an advisor how to organise files, and what does not work yet. */
class UploadPageInstructionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_upload_page_explains_client_folders_and_what_is_not_supported_yet(): void
    {
        Storage::fake('portfolios');

        $response = $this->actingAs(activeSubscriberUser())->get(route('portfolio.upload'));

        $response->assertOk()
            ->assertSee('one folder per client', false)
            ->assertSee("The folder name becomes the client's name.", false)
            ->assertSee('They are combined into one portfolio and one report.')
            ->assertSee('PDF statements are not supported yet.')
            ->assertSee('US-dollar values are converted to rupees at the rate shown on your report.')
            ->assertSee("Some US broker exports don't include current prices; those holdings are valued at what was paid and are left out of the gain/loss figure.", false)
            ->assertDontSee('US-dollar exports are not supported yet.')
            // The drop zone names only what can actually be uploaded.
            ->assertSee('CSV, XLSX, XLS or ZIP — up to 20 MB')
            ->assertDontSee('XLS, PDF')
            ->assertSee("Put each client's files directly in their folder — no folders inside it.", false)
            ->assertSee('Up to 20 files per client');
    }
}
