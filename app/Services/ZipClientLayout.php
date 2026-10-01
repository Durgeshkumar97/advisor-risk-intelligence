<?php

namespace App\Services;

/**
 * ZipClientLayout
 *
 * How a multi-client ZIP's entry names are read. Shared by the upload-time
 * quota check and the extraction job so both agree on what a client is.
 *
 *   clients.zip
 *   ├── Rajesh Kumar/…        top-level folder = one client; its name is the
 *   │                         client's name, however many files it holds
 *   └── someone.csv           a file at the root = one client, named from the
 *                             filename (the pre-folder behaviour, unchanged)
 *
 * Entry names are untrusted input. Nothing here touches the filesystem, and a
 * folder name is only ever used as a display name and a grouping key.
 */
class ZipClientLayout
{
    public const MAX_CLIENTS = 1000;

    public const MAX_FILES_PER_CLIENT = 20;

    private const IGNORED_FILENAMES = ['thumbs.db', 'desktop.ini'];

    /**
     * Path segments of an entry name, without empty parts.
     *
     * @return list<string>
     */
    public static function segments(string $entryName): array
    {
        return array_values(array_filter(
            preg_split('#[\\\\/]+#', $entryName),
            fn ($segment) => $segment !== '',
        ));
    }

    /** A directory entry — it holds nothing itself. */
    public static function isDirectory(string $entryName): bool
    {
        return str_ends_with($entryName, '/') || str_ends_with($entryName, '\\');
    }

    /**
     * Operating-system clutter that is never a client file: anything under
     * __MACOSX, any dot-file or dot-folder, "__"-prefixed files, Thumbs.db.
     */
    public static function isIgnored(string $entryName): bool
    {
        $segments = self::segments($entryName);

        if ($segments === []) {
            return true;
        }

        foreach ($segments as $segment) {
            if (str_starts_with($segment, '.') || $segment === '__MACOSX') {
                return true;
            }
        }

        $filename = end($segments);

        return str_starts_with($filename, '__')
            || in_array(strtolower($filename), self::IGNORED_FILENAMES, true);
    }

    /** The top-level folder an entry sits in, or null for a file at the root. */
    public static function folder(string $entryName): ?string
    {
        $segments = self::segments($entryName);

        return count($segments) > 1 ? $segments[0] : null;
    }

    /** Deeper than <folder>/<file>: intermediate folders are ignored for identity. */
    public static function isNested(string $entryName): bool
    {
        return count(self::segments($entryName)) > 2;
    }

    public static function filename(string $entryName): string
    {
        $segments = self::segments($entryName);

        return $segments === [] ? '' : end($segments);
    }

    /**
     * How a file is shown to the advisor: its path inside the client folder
     * ("groww/holdings.csv"), or just the filename for a file directly in the
     * folder or at the ZIP root. Used for display only; skip reasons are keyed
     * by the full entry name, which is unique within a ZIP.
     */
    public static function labelWithinClient(string $entryName): string
    {
        $segments = self::segments($entryName);

        return count($segments) > 1 ? implode('/', array_slice($segments, 1)) : self::filename($entryName);
    }

    /**
     * The client name a folder stands for: trimmed, underscores and runs of
     * whitespace collapsed to single spaces. Capitalisation is preserved —
     * title-casing would mangle "D'Souza", "McLeod" or "NRI - Rajesh K".
     */
    public static function clientName(string $folder): string
    {
        return trim(preg_replace('/[\s_]+/u', ' ', $folder));
    }
}
