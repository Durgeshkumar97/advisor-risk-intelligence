<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\AssembleBundleZip;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * AssembleBundleZip had NO test coverage at all, because its child lookup used
 * raw MySQL JSON functions (JSON_UNQUOTE) that do not exist in the SQLite this
 * suite runs on — every attempt to exercise it died on
 * "no such function: JSON_UNQUOTE". Switching to Laravel's meta->key operator,
 * which compiles per driver, is what makes these tests possible.
 */
class AssembleBundleZipTest extends TestCase
{
    use RefreshDatabase;

    private function parentZip(User $user): PortfolioFile
    {
        return PortfolioFile::create([
            'user_id' => $user->id,
            'original_name' => 'clients.zip',
            'stored_name' => 'clients.zip',
            'path' => 'uploads/clients.zip',
            'mime_type' => 'application/zip',
            'file_size' => 500,
            'status' => PortfolioFile::STATUS_PROCESSING,
            'meta' => ['extension' => 'zip'],
        ]);
    }

    private function child(User $user, PortfolioFile $parent, string $clientName, bool $withReport): PortfolioFile
    {
        $reportPath = null;

        if ($withReport) {
            $reportPath = 'reports/'.\Illuminate\Support\Str::slug($clientName, '_').'.pdf';
            Storage::disk('portfolios')->put($reportPath, '%PDF-1.4 '.$clientName);
        }

        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => $clientName]);

        return PortfolioFile::create([
            'user_id' => $user->id,
            'portfolio_id' => $portfolio->id,
            'original_name' => $clientName.'.csv',
            'stored_name' => $clientName.'.csv',
            'path' => 'uploads/'.$clientName.'.csv',
            'report_path' => $reportPath,
            'mime_type' => 'text/csv',
            'file_size' => 50,
            'status' => $withReport ? PortfolioFile::STATUS_PROCESSED : PortfolioFile::STATUS_FAILED,
            'meta' => [
                'extracted_from_zip_id' => $parent->id,
                'client_name' => $clientName,
            ],
        ]);
    }

    public function test_it_finds_its_children_by_json_meta_and_bundles_their_reports(): void
    {
        Storage::fake('portfolios');
        Mail::fake();

        $user = User::factory()->create();
        $parent = $this->parentZip($user);

        $this->child($user, $parent, 'Rajesh Sharma', withReport: true);
        $this->child($user, $parent, 'Priya Nair', withReport: true);
        $this->child($user, $parent, 'Failed Client', withReport: false);

        // A child of a DIFFERENT parent must not be swept into this bundle —
        // this is what the JSON lookup has to get right.
        $otherParent = $this->parentZip($user);
        $this->child($user, $otherParent, 'Someone Elses Client', withReport: true);

        AssembleBundleZip::dispatchSync($parent->id);

        $parent->refresh();

        $this->assertSame(PortfolioFile::STATUS_PROCESSED, $parent->status);
        $this->assertNotNull($parent->bundle_report_path, 'A bundle should have been written.');
        $this->assertTrue(Storage::disk('portfolios')->exists($parent->bundle_report_path));

        $this->assertSame(2, $parent->meta['bundle_processed_count']);
        $this->assertSame(1, $parent->meta['bundle_failed_count']);
    }

    public function test_the_bundle_contains_only_this_parents_reports_plus_a_summary(): void
    {
        Storage::fake('portfolios');
        Mail::fake();

        $user = User::factory()->create();
        $parent = $this->parentZip($user);
        $this->child($user, $parent, 'Rajesh Sharma', withReport: true);

        $otherParent = $this->parentZip($user);
        $this->child($user, $otherParent, 'Someone Elses Client', withReport: true);

        AssembleBundleZip::dispatchSync($parent->id);

        $parent->refresh();

        $localZip = tempnam(sys_get_temp_dir(), 'bundle_').'.zip';
        file_put_contents($localZip, Storage::disk('portfolios')->get($parent->bundle_report_path));

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($localZip) === true);

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }
        $zip->close();
        @unlink($localZip);

        sort($entries);

        $this->assertSame(['_SUMMARY.txt', 'rajesh_sharma_report.pdf'], $entries);
    }

    public function test_it_exits_without_a_bundle_when_the_parent_has_no_children(): void
    {
        Storage::fake('portfolios');
        Mail::fake();

        $user = User::factory()->create();
        $parent = $this->parentZip($user);

        AssembleBundleZip::dispatchSync($parent->id);

        $this->assertNull($parent->fresh()->bundle_report_path);
    }
}
