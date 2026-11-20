<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_archive_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->index();
            $table->string('source_table');
            $table->unsignedBigInteger('source_id');
            $table->unique(['source_table', 'source_id']);
        });
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS performance_pack_scope');
            DB::statement("CREATE TRIGGER performance_pack_active_scope BEFORE INSERT ON performance_report_packs WHEN EXISTS (SELECT 1 FROM performance_report_packs AS previous WHERE previous.user_id = NEW.user_id AND previous.frequency = NEW.frequency AND previous.period_start = NEW.period_start AND previous.period_end = NEW.period_end AND NOT EXISTS (SELECT 1 FROM reporting_archive_records AS archived WHERE archived.source_table = 'performance_report_packs' AND archived.source_id = previous.id)) BEGIN SELECT RAISE(ABORT, 'An active report pack already exists for this scope'); END");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Archive membership retains immutable reporting history; restore a batch explicitly.');
    }
};
