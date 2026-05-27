<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Admin model — CURRENTLY ORPHANED.
 *
 * config/auth.php uses App\Models\User for BOTH the 'web' and 'admin' guards.
 * Admin access is controlled by the `is_admin` boolean on the users table,
 * not by a separate admin table/model.
 *
 * This model was likely an early design that was superseded by is_admin on User.
 * Do NOT use it for new auth logic. If a separate admin table is needed in the
 * future, wire it into a dedicated provider in config/auth.php first.
 *
 * @deprecated  Use User::where('is_admin', true) or the AdminOnly middleware.
 */
class Admin extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'password',
    ];
}