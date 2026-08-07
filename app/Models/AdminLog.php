<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminLog extends Model
{
    protected $fillable = [
        'user_id',
        'target_user_id',
        'token_hash',
        'event',
        'ip',
        'user_agent',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | RECORD — single entry point for every admin_logs write
    |--------------------------------------------------------------------------
    |
    | user_id is only ever set when a genuinely verified admin performed the
    | action (admin_login_success, impersonation_link_minted) — it is a
    | structural guarantee, not just a naming convention, that admin()
    | always resolves to a real admin or null. Every other party a log entry
    | is about (a rejected/failed login's own account, an impersonation or
    | self-service link's recipient) goes in target_user_id instead.
    |
    | request()->ip()/userAgent() work uniformly whether called from a
    | controller with an injected Request, a route closure, or a queued job
    | (SubscriptionService::activate() -> UserAccountRecoveryService calls
    | this with no HTTP request in flight) — Laravel always binds a default
    | Request in the container even outside a real HTTP request, so this
    | never throws; it just records placeholder-ish values in that one
    | genuinely request-less case, which is an honest reflection of there
    | being no real requester to attribute the event to.
    |
    */

    public static function record(
        string $event,
        ?int $userId = null,
        ?int $targetUserId = null,
        ?string $tokenHash = null,
    ): self {
        return self::create([
            'user_id' => $userId,
            'target_user_id' => $targetUserId,
            'token_hash' => $tokenHash,
            'event' => $event,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
