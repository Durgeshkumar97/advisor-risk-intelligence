<?php

declare(strict_types=1);

// Declared in Tests\Feature (not Tests\Support, which this file's directory
// would otherwise imply) so every Feature test that require_once's this file
// can call these functions by their bare names, unchanged from how each
// test file already calls its own local helpers. This is require_once'd
// directly rather than relying on Composer's PSR-4 autoloader, which only
// autoloads classes/interfaces/traits, never plain function files.

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

/**
 * Active Team-plan subscriber with a high monthly_client_limit — shared by
 * tests that need an authenticated, subscription-gated user (portfolio
 * upload / ZIP handling), not just any authenticated user.
 */
function activeSubscriberUser(): User
{
    $plan = Plan::create([
        'name' => 'Team',
        'slug' => 'team-'.uniqid(),
        'price' => 999,
        'duration_days' => 30,
        'portfolio_limit' => 100,
        'trial_days' => 0,
        'is_active' => true,
        'monthly_client_limit' => 1000,
    ]);

    $user = User::factory()->create();

    Subscription::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
        'renewal_at' => now()->addDays(30),
        'provider' => 'razorpay',
    ]);

    return $user;
}

/** Minimal Plan row — only the fields the migration requires non-null. */
function minimalActivePlan(): Plan
{
    return Plan::create([
        'name' => 'Starter',
        'slug' => 'starter',
        'price' => '999.00',
        'duration_days' => 30,
        'is_active' => true,
    ]);
}

/**
 * Minimal valid XLSX (an OOXML zip container) — just enough internal
 * structure ([Content_Types].xml declaring the spreadsheet content type,
 * _rels/.rels, xl/workbook.xml) for finfo to reliably detect it as a real
 * xlsx file, without needing a full spreadsheet library dependency.
 */
function minimalValidXlsxContent(): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_').'.xlsx';
    $zip = new \ZipArchive;
    $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<workbook/>');
    $zip->close();

    $content = file_get_contents($tmp);
    unlink($tmp);

    return $content;
}

/** Admin user with a known plaintext password, for admin-login-flow tests. */
function adminUserFixture(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'is_admin' => true,
        'password' => bcrypt('correct-password'),
    ], $overrides));
}
