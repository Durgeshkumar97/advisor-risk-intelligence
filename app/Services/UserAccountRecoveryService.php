<?php

namespace App\Services;

use App\Models\User;
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
}
