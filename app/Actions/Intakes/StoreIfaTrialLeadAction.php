<?php

namespace App\Actions\Intakes;

use App\Models\ClientIntake;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StoreIfaTrialLeadAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(
        array $validated,
        ?UploadedFile $document = null,
    ): ?ClientIntake {
        $documentPath = null;

        try {
            return DB::transaction(function () use (
                $validated,
                $document,
                &$documentPath,
            ): ?ClientIntake {
                if ($this->duplicateExists($validated)) {
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
                    'trial_ends_at' => now()->addDays(14),
                    'plan_price' => 0,
                    'revenue_generated' => 0,
                    'lead_score' => 50,
                    'ai_status' => 'pending',
                ]);

                Log::info('IFA free trial lead stored.', [
                    'client_intake_id' => $intake->getKey(),
                    'submission_uuid' => $intake->submission_uuid,
                    'email_hash' => $this->hashValue($intake->email),
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
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function duplicateExists(array $validated): bool
    {
        return ClientIntake::query()
            ->where('email', $validated['email'])
            ->orWhere('whatsapp', $validated['whatsapp'])
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

    private function hashValue(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return hash('sha256', Str::lower($value));
    }
}
