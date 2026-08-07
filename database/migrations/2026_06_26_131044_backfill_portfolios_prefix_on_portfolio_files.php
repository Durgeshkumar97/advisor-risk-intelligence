<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PREFIX = 'portfolios/';

    private const COLUMNS = ['path', 'report_path', 'bundle_report_path'];

    public function up(): void
    {
        $driver = DB::getDriverName();

        foreach (self::COLUMNS as $col) {
            DB::table('portfolio_files')
                ->whereNotNull($col)
                ->where($col, 'not like', self::PREFIX.'%')
                ->update([
                    $col => DB::raw($this->concatExpr($driver, $col)),
                ]);
        }
    }

    public function down(): void
    {
        // SUBSTR is 1-indexed; skip past the prefix length.
        $offset = strlen(self::PREFIX) + 1;

        foreach (self::COLUMNS as $col) {
            DB::table('portfolio_files')
                ->whereNotNull($col)
                ->where($col, 'like', self::PREFIX.'%')
                ->update([
                    $col => DB::raw("SUBSTR({$col}, {$offset})"),
                ]);
        }
    }

    private function concatExpr(string $driver, string $col): string
    {
        $prefix = "'".self::PREFIX."'";

        return match ($driver) {
            'sqlite' => "{$prefix} || {$col}",
            default => "CONCAT({$prefix}, {$col})",
        };
    }
};
