<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the dashboard after login.
     */
    public const HOME = '/dashboard';

    /**
     * Define route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | API RATE LIMITER
        |--------------------------------------------------------------------------
        */

        RateLimiter::for('api', function (Request $request) {

            return Limit::perMinute(60)
                ->by(
                    $request->user()?->id
                        ?: $request->ip()
                );
        });

        /*
        |--------------------------------------------------------------------------
        | PAYMENT RATE LIMITER
        |--------------------------------------------------------------------------
        */

        RateLimiter::for('payments', function (Request $request) {

            return Limit::perMinute(20)
                ->by($request->ip());
        });

        /*
        |--------------------------------------------------------------------------
        | ROUTES
        |--------------------------------------------------------------------------
        */

        $this->routes(function () {

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
