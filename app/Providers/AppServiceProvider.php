<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | DEFAULT STRING LENGTH
        |--------------------------------------------------------------------------
        */

        Schema::defaultStringLength(191);

        /*
        |--------------------------------------------------------------------------
        | FORCE HTTPS IN PRODUCTION
        |--------------------------------------------------------------------------
        */

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
