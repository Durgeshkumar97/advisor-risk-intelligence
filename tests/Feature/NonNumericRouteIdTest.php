<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/../Support/SharedFixtures.php';

uses(\Tests\TestCase::class, RefreshDatabase::class);

/*
 * Every {id} route hands its parameter to a controller method typed
 * `int $id`. With no constraint on the route, /file/abc matched, PHP could
 * not coerce "abc" to an int, and the request died with a TypeError — a 500
 * and an error report for what is simply a URL that points at nothing.
 *
 * routes/web.php now declares Route::pattern('id', '[0-9]{1,18}'), so such a
 * URL matches no route at all.
 *
 * Each of these reached a controller and threw before the fix:
 *   abc                    not a number
 *   1.5                    numeric, but not an integer
 *   99999999999999999999   an integer too large for a 64-bit int
 */

$badIds = ['abc', '1.5', '99999999999999999999'];

$advisorRoutes = [
    ['get', '/file/%s'],
    ['get', '/report/%s'],
    ['get', '/report/%s/download'],
    ['get', '/report/%s/bundle'],
    ['get', '/portfolios/%s/risk-profile'],
    ['post', '/portfolios/%s/risk-profile'],
    ['patch', '/portfolios/%s'],
    ['delete', '/portfolios/%s'],
    ['delete', '/portfolio/file/%s'],
];

$adminRoutes = [
    ['get', '/admin/users/%s'],
    ['post', '/admin/users/%s/login-link'],
    ['get', '/admin/intakes/%s'],
    ['post', '/admin/intakes/%s/status'],
];

/*
 * What "refused" looks like depends on the method. A GET that matches no
 * route lands on the app's fallback route and gets its 404 page. The
 * fallback only answers GET, and it matches any path, so a POST, PATCH or
 * DELETE to the same URL is told 405 instead — as it is for any other URL the
 * app does not have. Either is a client error; the 500 is what must not
 * come back.
 */
$assertRefused = function (string $method, string $uri): void {
    $status = test()->{$method}($uri)->getStatusCode();

    $method === 'get'
        ? expect($status)->toBe(404, "GET {$uri}")
        : expect($status)->toBeIn([404, 405], strtoupper($method)." {$uri}");
};

describe('a route id that is not an integer is refused, not a server error', function () use ($badIds, $advisorRoutes, $adminRoutes, $assertRefused) {

    it('refuses it on every advisor route', function (string $method, string $uri) use ($badIds, $assertRefused) {
        // Subscribed, so neither `auth` nor `paid` can be what answers.
        $this->actingAs(activeSubscriberUser());

        foreach ($badIds as $id) {
            $assertRefused($method, sprintf($uri, $id));
        }
    })->with($advisorRoutes);

    it('refuses it on every admin route', function (string $method, string $uri) use ($badIds, $assertRefused) {
        $this->actingAs(User::factory()->create(['is_admin' => 1]));

        foreach ($badIds as $id) {
            $assertRefused($method, sprintf($uri, $id));
        }
    })->with($adminRoutes);
});

describe('the id constraint does not get in the way of a real id', function () {

    // The control for the 404s above: without it they would also pass if
    // the pattern were so tight that nothing matched at all.

    it('still serves an advisor route for an integer id', function () {
        $advisor = activeSubscriberUser();
        $portfolio = Portfolio::create(['user_id' => $advisor->id, 'name' => 'Control']);

        $this->actingAs($advisor)
            ->get("/portfolios/{$portfolio->id}/risk-profile")
            ->assertOk();
    });

    it('still serves an admin route for an integer id', function () {
        $admin = User::factory()->create(['is_admin' => 1]);
        $lead = Lead::create(['name' => 'Control Lead', 'phone' => '9000000000', 'status' => 'new']);

        $this->actingAs($admin)->get("/admin/users/{$admin->id}")->assertOk();
        $this->actingAs($admin)->get("/admin/intakes/{$lead->id}")->assertOk();
    });

    it('still 404s — by lookup, not by pattern — for an integer id that does not exist', function () {
        $this->actingAs(activeSubscriberUser())->get('/file/99999999')->assertNotFound();
    });
});
