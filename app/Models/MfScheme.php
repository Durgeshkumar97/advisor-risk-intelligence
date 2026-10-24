<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of AMFI's scheme master (NAVAll.txt), keyed by AMFI scheme code.
 *
 * Rows are upserted by amfi:sync and never deleted: a scheme that has left
 * the file (merged, wound up) must still match the old holdings that name it.
 */
class MfScheme extends Model
{
    protected $primaryKey = 'scheme_code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'scheme_code', 'isin_growth', 'isin_reinvest', 'scheme_name', 'normalized_name',
        'former_name_normalized', 'amc', 'scheme_type', 'broad_category', 'sub_category',
        'latest_nav', 'nav_date',
    ];

    protected $casts = [
        'nav_date' => 'date',
        'latest_nav' => 'float',
    ];
}
