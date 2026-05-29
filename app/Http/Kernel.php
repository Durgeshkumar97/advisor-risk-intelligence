<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * Global HTTP middleware
     */
    protected $middleware = [

        // Trust proxies (important for load balancers)
        \App\Http\Middleware\TrustProxies::class,

        // Host protection
        \App\Http\Middleware\TrustHosts::class,

        // CORS
        \Illuminate\Http\Middleware\HandleCors::class,

        // Maintenance mode
        \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,

        // Request limits
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,

        // Input cleanup
        \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];


    /**
     * Route middleware groups
     */
    protected $middlewareGroups = [

        'web' => [

            // Cookies
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,

            // Sessions
            \Illuminate\Session\Middleware\StartSession::class,

            // CSRF
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,

            // Errors & bindings
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [

            // Rate limit API
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':60,1',

            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];


    /**
     * Route middleware
     */
    protected $routeMiddleware = [

        // Laravel defaults
        'auth' => \App\Http\Middleware\Authenticate::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,

        // Custom
        'admin' => \App\Http\Middleware\AdminOnly::class,

        // SaaS core
        'paid' => \App\Http\Middleware\CheckSubscription::class,
        'active.sub' => \App\Http\Middleware\EnsureActiveSubscription::class,
    ];


    /**
     * Middleware priority (VERY IMPORTANT)
     */
    protected $middlewarePriority = [

        \Illuminate\Session\Middleware\StartSession::class,

        \Illuminate\View\Middleware\ShareErrorsFromSession::class,

        \App\Http\Middleware\Authenticate::class,

        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ];
}