<?php

namespace App\Policies;

use App\Models\PortfolioFile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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

    public function view(User $user, PortfolioFile $file): Response
    {
        return $user->id === $file->user_id
            ? Response::allow()
            : Response::denyWithStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    | Only the owner may delete their own file.
    */

    public function delete(User $user, PortfolioFile $file): Response
    {
        return $user->id === $file->user_id
            ? Response::allow()
            : Response::denyWithStatus(404);
    }
}
