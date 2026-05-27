<?php

namespace App\Policies;

use App\Models\Portfolio;
use App\Models\User;

/**
 * Authorises Portfolio actions.
 * Register in AuthServiceProvider::$policies if not using auto-discovery.
 *
 * Usage:
 *   $this->authorize('view',   $portfolio);
 *   $this->authorize('update', $portfolio);
 *   $this->authorize('delete', $portfolio);
 */
class PortfolioPolicy
{
    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function view(User $user, Portfolio $portfolio): bool
    {
        return $user->id === $portfolio->user_id;
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    | Any authenticated user may create a portfolio.
    */

    public function create(User $user): bool
    {
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(User $user, Portfolio $portfolio): bool
    {
        return $user->id === $portfolio->user_id;
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function delete(User $user, Portfolio $portfolio): bool
    {
        return $user->id === $portfolio->user_id;
    }
}
