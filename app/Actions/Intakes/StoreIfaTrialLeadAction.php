<?php

namespace App\Actions\Intakes;

use App\Models\ClientIntake;
use App\Models\Plan;
use App\Models\Portfolio;
use App\Models\User;
use App\Services\Notifications\WelcomeSetPasswordNotification;
use App\Services\UserAccountRecoveryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StoreIfaTrialLeadAction
{
    public function __construct(
        private readonly UserAccountRecoveryService $accounts,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array{intake: ClientIntake, user: User, created: bool}|null
     *                                                                     Returns null on duplicate submission (existing live user).
     */
    public function execute(
        array $validated,
        ?UploadedFile $document = null,
    ): ?array {
        $documentPath = null;
        $newUser = null;   // captured via reference — read AFTER transaction commits
        $setPasswordUrl = null;   // captured via reference — read AFTER transaction commits
        $committedUser = null;   // captured via reference — User object for auto-login
        $restoredExistingUser = null;   // captured via reference — notified AFTER transaction commits

        try {
            $intake = DB::transaction(function () use (
                $validated,
                $document,
                &$documentPath,
                &$newUser,
                &$setPasswordUrl,
                &$committedUser,
                &$restoredExistingUser,
            ): ?ClientIntake {
                $plan = Plan::where('slug', 'starter')->firstOrFail();

                if ($this->duplicateExists($validated)) {
                    $existingUser = User::withTrashed()
                        ->where('email', Str::lower($validated['email']))
                        ->first();

                    if ($existingUser?->trashed()) {
                        $result = $this->restoreOrCreateTrialUser($validated);
                        $user = $result['user'];

                        $this->ensureTrialSubscription($user, $plan);

                        // No session is granted here (this branch returns null,
                        // so IntakeController never auto-logs in), but the real
                        // owner must still be told their soft-deleted account
                        // was just restored by an unauthenticated submission.
                        $restoredExistingUser = $user;

                        Log::info('Duplicate IFA free trial lead restored soft-deleted user.', [
                            'email_hash' => $this->hashValue($validated['email']),
                            'user_id' => $user->id,
                            'user_was_restored' => $result['restored'],
                        ]);

                        return null;
                    }

                    Log::info('Duplicate IFA free trial lead ignored.', [
                        'email_hash' => $this->hashValue($validated['email']),
                        'whatsapp_hash' => $this->hashValue($validated['whatsapp']),
                    ]);

                    return null;
                }

                $documentPath = $this->storeDocument($document);

                $intake = ClientIntake::query()->create([
                    'submission_uuid' => (string) Str::uuid(),
                    'name' => $validated['advisor_name'],
                    'email' => $validated['email'],
                    'whatsapp' => $validated['whatsapp'],
                    'firm_name' => $validated['firm_name'],
                    'document_path' => $documentPath,
                    'plan' => null,
                    'status' => 'trial',
                    'trial_started_at' => now(),
                    'trial_ends_at' => now()->addDays($plan->trial_days),
                    'plan_price' => 0,
                    'revenue_generated' => 0,
                    'lead_score' => 50,
                    'ai_status' => 'pending',
                ]);

                $result = $this->restoreOrCreateTrialUser($validated);
                $user = $result['user'];

                $this->ensureTrialSubscription($user, $plan);

                if ($result['created']) {
                    Portfolio::create([
                        'user_id' => $user->id,
                        'name' => 'My Portfolio',
                        'total_value' => 0,
                        'risk_score' => 0,
                        'risk_level' => 'LOW',
                    ]);

                    $resetToken = Password::broker()->createToken($user);
                    $setPasswordUrl = route('password.reset', ['token' => $resetToken])
                        .'?email='.urlencode($user->email);
                    $newUser = $user;
                }

                $committedUser = $user;

                Log::info('IFA free trial lead stored.', [
                    'client_intake_id' => $intake->getKey(),
                    'submission_uuid' => $intake->submission_uuid,
                    'email_hash' => $this->hashValue($intake->email),
                    'user_id' => $user->id,
                    'user_was_created' => $result['created'],
                    'user_was_restored' => $result['restored'],
                ]);

                return $intake;
            });
        } catch (Throwable $exception) {
            if ($documentPath !== null) {
                Storage::delete($documentPath);
            }

            Log::error('IFA free trial lead storage failed.', [
                'email_hash' => $this->hashValue($validated['email'] ?? null),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        // Dispatched OUTSIDE the transaction — only fires after a successful commit.
        // If the transaction rolls back, $newUser stays null and no email is sent.
        if ($newUser !== null && $setPasswordUrl !== null) {
            $newUser->notify(new WelcomeSetPasswordNotification($setPasswordUrl));
        }

        if ($restoredExistingUser !== null) {
            $this->accounts->sendLoginLinkToExistingUser($restoredExistingUser);
        }

        if ($intake === null || $committedUser === null) {
            return null;
        }

        return [
            'intake' => $intake,
            'user' => $committedUser,
            'created' => $newUser !== null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function duplicateExists(array $validated): bool
    {
        return ClientIntake::query()
            ->where('email', $validated['email'])
            ->when(
                ! empty($validated['whatsapp']),
                fn ($q) => $q->orWhere('whatsapp', $validated['whatsapp']),
            )
            ->exists();
    }

    private function storeDocument(?UploadedFile $document): ?string
    {
        if ($document === null) {
            return null;
        }

        $path = $document->store('private_uploads');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('IFA lead document storage failed.');
        }

        return $path;
    }

    private function hasActiveOrTrialSubscription(User $user): bool
    {
        return $user->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{user: User, created: bool, restored: bool}
     */
    private function restoreOrCreateTrialUser(array $validated): array
    {
        return $this->accounts->findRestoreOrCreateUserByEmail(
            $validated['email'],
            [
                'name' => $validated['advisor_name'],
                'password' => Hash::make(str()->random(32)),
            ],
            [
                'name' => $validated['advisor_name'],
            ],
        );
    }

    private function ensureTrialSubscription(User $user, Plan $plan): void
    {
        if ($this->hasActiveOrTrialSubscription($user)) {
            return;
        }

        $user->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => 'trial',
            'trial_started_at' => now(),
            'trial_ends_at' => now()->addDays($plan->trial_days),
        ]);
    }

    private function hashValue(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return hash('sha256', Str::lower($value));
    }
}
