<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;

class PortfolioFileType implements ValidationRule
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'csv', 'xlsx', 'xls', 'zip'];

    /**
     * The actual content is the real check (via Symfony's finfo-backed
     * guessExtension(), same mechanism Laravel's own mimes: rule uses
     * internally) — the claimed extension is never trusted on its own, it
     * only decides whether the text/plain carve-out applies. csv gets that
     * carve-out because it has no binary magic signature, and a single-row
     * CSV commonly detects as text/plain rather than text/csv (empirically
     * confirmed: finfo's csv-vs-plain-text heuristic needs multiple rows
     * sharing a delimiter pattern before it's confident). A PHP payload or
     * arbitrary binary disguised as .csv still fails both checks, so this
     * only tolerates the txt-vs-csv ambiguity, not genuinely dangerous
     * content.
     *
     * $allowedExtensions is a parameter, not a shared constant, because the
     * two callers have genuinely different policies: the primary upload
     * (this rule) accepts a top-level .zip; ProcessPortfolioFile::
     * handleZipExtraction() must not accept a nested .zip inside a .zip, so
     * it passes its own, narrower list.
     *
     * guessExtension()/getMimeType() throw on a file that isn't actually
     * readable — e.g. a failed upload (oversized, interrupted transfer)
     * that still reports a non-empty path. Laravel's old mimes: rule never
     * reached that point for such files (it checks isValid() first); this
     * doesn't, so it's caught here instead and reported as unacceptable
     * rather than propagating.
     *
     * @param  list<string>  $allowedExtensions
     * @return array{acceptable: bool, detectedExtension: ?string, detectedMimeType: ?string}
     */
    public static function contentMatchesAllowedType(File $file, string $claimedExtension, array $allowedExtensions): array
    {
        try {
            $detectedExtension = $file->guessExtension();
            $detectedMimeType = $file->getMimeType();
        } catch (\Throwable $e) {
            return ['acceptable' => false, 'detectedExtension' => null, 'detectedMimeType' => null];
        }

        $acceptable = in_array($detectedExtension, $allowedExtensions, true)
            || ($claimedExtension === 'csv' && $detectedMimeType === 'text/plain');

        return [
            'acceptable' => $acceptable,
            'detectedExtension' => $detectedExtension,
            'detectedMimeType' => $detectedMimeType,
        ];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || $value->getPath() === '') {
            $fail('Only PDF, CSV, XLSX, XLS, and ZIP files are allowed.');

            return;
        }

        $claimedExtension = strtolower($value->getClientOriginalExtension());

        $result = self::contentMatchesAllowedType($value, $claimedExtension, self::ALLOWED_EXTENSIONS);

        if (! $result['acceptable']) {
            $fail('Only PDF, CSV, XLSX, XLS, and ZIP files are allowed.');
        }
    }
}
