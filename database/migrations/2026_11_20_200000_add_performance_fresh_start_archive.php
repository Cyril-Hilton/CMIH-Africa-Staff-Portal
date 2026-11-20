<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (config('reporting_archive.performance_models') as $model) {
            $name = (new $model)->getTable();
            if (! Schema::hasTable($name)) {
                continue;
            }
            Schema::table($name, fn (Blueprint $t) => $t->uuid('reporting_archive_batch_id')->nullable()->index());
            if (DB::connection()->getDriverName() === 'sqlite') {
                foreach (Schema::getIndexes($name) as $index) {
                    if (! $index['unique'] || $index['primary'] || str_starts_with($index['name'], 'sqlite_autoindex')) {
                        continue;
                    }
                    $quote = fn ($s) => '"'.str_replace('"', '""', $s).'"';
                    DB::statement('DROP INDEX '.$quote($index['name']));
                    DB::statement('CREATE UNIQUE INDEX '.$quote($index['name']).' ON '.$quote($name).' ('.implode(',', array_map($quote, $index['columns'])).') WHERE reporting_archive_batch_id IS NULL');
                }
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Performance history is retained; restore the archive batch instead of dropping its schema.');
    }
};
