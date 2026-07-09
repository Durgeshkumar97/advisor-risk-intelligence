<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class PortfolioFileType implements ValidationRule
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'csv', 'xlsx', 'xls', 'zip'];

    /**
     * Same content-based check as ProcessPortfolioFile::handleZipExtraction()
     * uses for ZIP-extracted entries: the actual content is the real check
     * (via Symfony's finfo-backed guessExtension(), same mechanism
     * Laravel's own mimes: rule uses internally). csv gets the same
     * carve-out for the same reason — it has no binary magic signature, and
     * a single-row CSV commonly detects as text/plain rather than text/csv
     * (empirically confirmed: finfo's csv-vs-plain-text heuristic needs
     * multiple rows sharing a delimiter pattern before it's confident). A
     * PHP payload or arbitrary binary disguised as .csv still fails both
     * checks, so this only tolerates the txt-vs-csv ambiguity, not
     * genuinely dangerous content. The claimed extension is never trusted
     * on its own — it only decides whether the text/plain carve-out
     * applies.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile || $value->getPath() === '') {
            $fail('Only PDF, CSV, XLSX, XLS, and ZIP files are allowed.');
            return;
        }

        $detectedExtension = $value->guessExtension();
        $claimedExtension  = strtolower($value->getClientOriginalExtension());

        $contentIsAcceptable = in_array($detectedExtension, self::ALLOWED_EXTENSIONS, true)
            || ($claimedExtension === 'csv' && $value->getMimeType() === 'text/plain');

        if (!$contentIsAcceptable) {
            $fail('Only PDF, CSV, XLSX, XLS, and ZIP files are allowed.');
        }
    }
}
