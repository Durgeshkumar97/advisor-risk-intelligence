<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

class MarketDashboardController extends Controller
{
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | Source File Path
        |--------------------------------------------------------------------------
        */

        $path = storage_path('app/global_strong_em_quarterly_returns.csv');

        /*
        |--------------------------------------------------------------------------
        | Fallback If File Missing
        |--------------------------------------------------------------------------
        */

        if (!file_exists($path)) {
            return view('dashboard', [
                'data'     => [],
                'quarters' => [],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Read CSV Data
        |--------------------------------------------------------------------------
        */

        $rows = array_map('str_getcsv', file($path));

        if (empty($rows)) {
            return view('dashboard', [
                'data'     => [],
                'quarters' => [],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Header / Quarter Labels
        |--------------------------------------------------------------------------
        */

        $header = $rows[0];

        unset($rows[0]);

        $quarters = array_slice($header, 3);

        /*
        |--------------------------------------------------------------------------
        | Format Dashboard Data
        |--------------------------------------------------------------------------
        */

        $data = [];

        foreach ($rows as $row) {

            if (count($row) < 4) {
                continue;
            }

            $data[] = [
                'country'  => $row[0] ?? null,
                'exchange' => $row[1] ?? null,
                'index'    => $row[2] ?? null,
                'returns'  => array_map(
                    'floatval',
                    array_slice($row, 3)
                ),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Render Market Dashboard
        |--------------------------------------------------------------------------
        */

        return view('dashboard', compact(
            'data',
            'quarters'
        ));
    }
}