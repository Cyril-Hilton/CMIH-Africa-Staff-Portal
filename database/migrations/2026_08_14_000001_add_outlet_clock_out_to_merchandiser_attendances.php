<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('merchandiser_attendances')) {
            return;
        }

        Schema::table('merchandiser_attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('merchandiser_attendances', 'clock_out_time')) {
                $table->dateTime('clock_out_time')->nullable()->after('clock_in_time');
            }

            if (! Schema::hasColumn('merchandiser_attendances', 'clock_out_latitude')) {
                $table->decimal('clock_out_latitude', 10, 8)->nullable()->after('distance_from_outlet');
            }

            if (! Schema::hasColumn('merchandiser_attendances', 'clock_out_longitude')) {
                $table->decimal('clock_out_longitude', 11, 8)->nullable()->after('clock_out_latitude');
            }

            if (! Schema::hasColumn('merchandiser_attendances', 'clock_out_distance_from_outlet')) {
                $table->decimal('clock_out_distance_from_outlet', 8, 2)->nullable()->after('clock_out_longitude');
            }

            if (! Schema::hasColumn('merchandiser_attendances', 'visit_duration_minutes')) {
                $table->unsignedInteger('visit_duration_minutes')->nullable()->after('clock_out_distance_from_outlet');
            }
        });

        Schema::table('merchandiser_attendances', function (Blueprint $table) {
            if (Schema::hasColumn('merchandiser_attendances', 'clock_out_time')) {
                $table->index(['user_id', 'outlet_id', 'clock_out_time'], 'merch_att_user_outlet_out_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('merchandiser_attendances')) {
            return;
        }

        Schema::table('merchandiser_attendances', function (Blueprint $table) {
            if (Schema::hasColumn('merchandiser_attendances', 'clock_out_time')) {
                $table->dropIndex('merch_att_user_outlet_out_idx');
            }

            foreach ([
                'visit_duration_minutes',
                'clock_out_distance_from_outlet',
                'clock_out_longitude',
                'clock_out_latitude',
                'clock_out_time',
            ] as $column) {
                if (Schema::hasColumn('merchandiser_attendances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
