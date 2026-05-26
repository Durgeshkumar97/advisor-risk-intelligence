<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'processed_at')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->timestamp('processed_at')
                    ->nullable()
                    ->after('paid_at');
            });
        }

        if (! $this->indexExists('payments', 'payments_processed_at_index')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index('processed_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'processed_at')) {
            Schema::table('payments', function (Blueprint $table) {
                if ($this->indexExists('payments', 'payments_processed_at_index')) {
                    $table->dropIndex(['processed_at']);
                }

                $table->dropColumn('processed_at');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if (method_exists($connection->getSchemaBuilder(), 'hasIndex')) {
            return Schema::hasIndex($table, $index);
        }

        if ($driver === 'mysql') {
            $result = DB::selectOne(
                'select count(*) as aggregate from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ?',
                [$table, $index]
            );

            return (int) $result->aggregate > 0;
        }

        if ($driver === 'sqlite') {
            $indexes = DB::select("pragma index_list('{$table}')");

            foreach ($indexes as $existing) {
                if (($existing->name ?? null) === $index) {
                    return true;
                }
            }
        }

        return false;
    }
};
