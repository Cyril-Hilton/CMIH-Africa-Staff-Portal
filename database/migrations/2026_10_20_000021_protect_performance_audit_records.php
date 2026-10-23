<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        foreach (['performance_events', 'performance_report_packs'] as $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $name = $table.'_immutable_'.strtolower($operation);
                if ($driver === 'sqlite') {
                    DB::statement("CREATE TRIGGER $name BEFORE $operation ON $table BEGIN SELECT RAISE(ABORT, 'Performance audit records are immutable'); END");
                } elseif ($driver === 'mysql') {
                    DB::unprepared("CREATE TRIGGER $name BEFORE $operation ON $table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Performance audit records are immutable'");
                }
            }
        }
    }

    public function down(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['sqlite', 'mysql'], true)) {
            return;
        }
        foreach (['performance_events', 'performance_report_packs'] as $table) {
            foreach (['update', 'delete'] as $operation) {
                DB::statement('DROP TRIGGER IF EXISTS '.$table.'_immutable_'.$operation);
            }
        }
    }
};
