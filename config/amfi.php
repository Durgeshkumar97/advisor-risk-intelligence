<?php

return [

    /*
    |--------------------------------------------------------------------------
    | NAVAll.txt source
    |--------------------------------------------------------------------------
    |
    | A value starting with http:// or https:// is downloaded; anything else is
    | read as a local file path. The path form is the fallback for when
    | production cannot reach amfiindia.com: push the file to e.g.
    | storage/app/amfi/NAVAll.txt and set AMFI_NAV_SOURCE to its absolute path
    | — no code change needed.
    */
    'source' => env('AMFI_NAV_SOURCE', 'https://www.amfiindia.com/spages/NAVAll.txt'),

    'timeout' => (int) env('AMFI_NAV_TIMEOUT', 60),

    /*
    | Sanity gates, checked before anything is written. A real file has
    | ~14,400 rows; a truncated or wrong download must not replace good data.
    */
    'min_rows' => 5000,
    'max_row_drop' => 0.20,   // vs the last successful run
    'max_age_days' => 4,
];
