<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts()
    {
        return [

            // Local development
            'localhost',
            '127.0.0.1',

            // Allow subdomains in local development
            '^(.+\.)?localhost$',

            // Your production domain 
            '^(.+\.)?risksignal\.in$',

        ];
    }
}