<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_archive_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('label');
            $table->unsignedBigInteger('actor_id');
            $table->json('manifest');
            $table->string('backup_path')->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('crm_account_directory', function (Blueprint $table) {
            $table->id();
            $table->string('client_name');
            $table->string('name_key')->unique();
            $table->timestamps();
        });
        foreach (array_merge(config('reporting_archive.models'), [App\Models\PerformanceOutbox::class]) as $model) {
            $name = (new $model)->getTable();
            if (! Schema::hasTable($name)) {
                continue;
            }
            Schema::table($name, fn (Blueprint $table) => $table->uuid('reporting_archive_batch_id')->nullable()->index());
            // A new reporting cycle may legitimately reuse a natural reporting scope/reference.
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
        throw new RuntimeException('Reporting archives contain retained business history. Restore a batch explicitly; do not drop the archive schema.');
    }
};
