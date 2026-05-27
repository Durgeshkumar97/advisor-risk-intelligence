<?php

namespace App\Policies;

use App\Models\PortfolioFile;
use App\Models\User;

/**
 * Authorises PortfolioFile actions.
 * Register in AuthServiceProvider::$policies if not using auto-discovery.
 *
 * Usage:
 *   $this->authorize('view',   $portfolioFile);
 *   $this->authorize('delete', $portfolioFile);
 */
class PortfolioFilePolicy
{
    /*
    |--------------------------------------------------------------------------
    | VIEW / DOWNLOAD
    |--------------------------------------------------------------------------
    */

    public function view(User $user, PortfolioFile $file): bool
    {
        return $user->id === $file->user_id;
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    | Only the owner may delete their own file.
    */

    public function delete(User $user, PortfolioFile $file): bool
    {
        return $user->id === $file->user_id;
    }
}
