<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioFile extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */

    protected $table = 'portfolio_files';

    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'user_id',

        'portfolio_id',

        'original_name',

        'stored_name',

        'path',

        'report_path',

        'bundle_report_path',

        'mime_type',

        'file_size',

        'status',

        'meta',

        'processed_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $casts = [

        'meta' => 'array',

        'processed_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | STATUS CONSTANTS
    |--------------------------------------------------------------------------
    */

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS CHECKS
    |--------------------------------------------------------------------------
    */

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isProcessed(): bool
    {
        return $this->status === self::STATUS_PROCESSED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /*
    |--------------------------------------------------------------------------
    | FAILURE MESSAGE — what the advisor is told
    |--------------------------------------------------------------------------
    |
    | meta.error_message holds either a reason written for the advisor ("Could
    | not find the column headers…") or, for an unexpected failure, this one
    | fixed sentence. The text of an exception is never stored there: it names
    | tables, columns, file paths and the database. It goes to the log and to
    | the exception handler instead.
    |
    */

    public const GENERIC_FAILURE_MESSAGE = 'Processing failed. Please check the file format or contact support.';

    /**
     * Marks of text that came from an exception rather than being written
     * for an advisor. Only needed for rows that failed before exception text
     * stopped being stored; nothing new is stored in this form.
     */
    private const EXCEPTION_TEXT = '/SQLSTATE|Connection:|SQL:|Stack trace|\.php\b|::|[A-Za-z]\\\\[A-Z]|Call to |Undefined |must be of type|Failed to open stream|No such file or directory|missing from storage|Allowed memory size|Maximum execution time|(?:^|[\s(])\/(?:home|var|tmp|usr)\//';

    /**
     * The failure reason to show the advisor, or null when there is none.
     * Always use this to display a failure; never print meta.error_message.
     */
    public function failureMessage(): ?string
    {
        $message = $this->meta['error_message'] ?? null;

        if (! is_string($message) || trim($message) === '') {
            return null;
        }

        return preg_match(self::EXCEPTION_TEXT, $message) === 1
            ? self::GENERIC_FAILURE_MESSAGE
            : $message;
    }

    /*
    |--------------------------------------------------------------------------
    | QUERIES
    |--------------------------------------------------------------------------
    */

    public static function monthlyClientCount(int $userId): int
    {
        // Portable equivalent of the old MySQL-only
        // COALESCE(JSON_EXTRACT(meta, '$.extension'), '') != 'zip' — a missing
        // meta.extension key (NULL) must still count as "not zip", same as
        // the original COALESCE-to-empty-string did. Written via the query
        // builder's JSON-path support so it works on both MySQL (production)
        // and SQLite (tests), instead of a MySQL-only raw JSON_UNQUOTE call.
        return static::where('user_id', $userId)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->where(function ($query) {
                $query->whereNull('meta->extension')
                    ->orWhere('meta->extension', '!=', 'zip');
            })
            // One client counts once. In a ZIP client folder the extra broker
            // files carry merged_into_file_id (the client's lead file); only
            // the lead is counted, however many files the folder holds.
            ->whereNull('meta->merged_into_file_id')
            ->count();
    }
}
