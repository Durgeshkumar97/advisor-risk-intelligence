<?php

namespace App\Services;

use App\Models\AdminLog;
use App\Models\User;
use App\Services\Notifications\ExistingAccountLoginLinkNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserAccountRecoveryService
{
    public const RESTORED_FLASH_KEY = 'restored_account';

    public const RESTORED_MESSAGE = 'Welcome back - your previous account and data have been restored.';

    /**
     * @param  array<string, mixed>  $createAttributes
     * @param  array<string, mixed>  $restoreAttributes
     * @return array{user: User, created: bool, restored: bool}
     */
    public function findRestoreOrCreateUserByEmail(
        string $email,
        array $createAttributes = [],
        array $restoreAttributes = [],
    ): array {
        $email = Str::lower($email);

        return DB::transaction(function () use ($email, $createAttributes, $restoreAttributes): array {
            $user = User::withTrashed()
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if ($user !== null) {
                $restored = $user->trashed();

                if ($restored) {
                    $user->restore();

                    if ($restoreAttributes !== []) {
                        $user->forceFill($restoreAttributes)->save();
                    }

                    $this->flashRestoredAccountMessage();
                }

                return [
                    'user' => $user,
                    'created' => false,
                    'restored' => $restored,
                ];
            }

            $user = User::create(array_merge(
                ['email' => $email],
                $createAttributes,
            ));

            return [
                'user' => $user,
                'created' => true,
                'restored' => false,
            ];
        });
    }

    private function flashRestoredAccountMessage(): void
    {
        if (app()->bound('session')) {
            session()->flash(self::RESTORED_FLASH_KEY, self::RESTORED_MESSAGE);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SEND LOGIN LINK TO EXISTING USER
    |--------------------------------------------------------------------------
    |
    | Used whenever an unauthenticated flow (trial signup, checkout) matches
    | an email to an EXISTING account. That account must never be auto-logged
    | into from an unauthenticated request — the submitter could be anyone
    | who merely typed in that email. Instead, a single-use magic-link token
    | is emailed to the account's own address (reusing the same login_token /
    | /auto-login/{token} mechanism the admin "send login link" feature
    | already uses — see AdminUserController::sendLoginLink()). The link is
    | NEVER returned in the HTTP response to the submitter.
    |
    | Logged as self_service_login_link_minted, distinct from
    | impersonation_link_minted — no admin is involved here at all, and
    | reusing the impersonation event name would mislabel routine
    | self-service logins as admin impersonation in the audit trail. This
    | method is also called from a queued job (SubscriptionService::activate()
    | via ProcessSuccessfulPayment), so AdminLog::record() may capture a
    | placeholder ip/user_agent rather than a real requester's — see its
    | docblock.
    |
    */

    public function sendLoginLinkToExistingUser(User $user): void
    {
        $token = Str::random(64);

        $user->forceFill([
            'login_token' => $token,
            'login_token_expires_at' => now()->addMinutes(15),
        ])->save();

        AdminLog::record(
            'self_service_login_link_minted',
            targetUserId: $user->id,
            tokenHash: hash('sha256', $token),
        );

        $user->notify(new ExistingAccountLoginLinkNotification(route('auto.login', $token)));
    }
}
