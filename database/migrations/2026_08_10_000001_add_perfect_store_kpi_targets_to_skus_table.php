<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            if (! Schema::hasColumn('skus', 'track_osa')) {
                $table->boolean('track_osa')->default(true)->after('category');
            }

            if (! Schema::hasColumn('skus', 'osa_drop_size')) {
                $table->unsignedInteger('osa_drop_size')->default(1)->after('track_osa');
            }

            if (! Schema::hasColumn('skus', 'track_npd')) {
                $table->boolean('track_npd')->default(false)->after('osa_drop_size');
            }

            if (! Schema::hasColumn('skus', 'npd_drop_size')) {
                $table->unsignedInteger('npd_drop_size')->default(1)->after('track_npd');
            }

            if (! Schema::hasColumn('skus', 'track_mhs')) {
                $table->boolean('track_mhs')->default(false)->after('npd_drop_size');
            }

            if (! Schema::hasColumn('skus', 'mhs_drop_size')) {
                $table->unsignedInteger('mhs_drop_size')->default(1)->after('track_mhs');
            }
        });
    }

    public function down(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            foreach (['track_osa', 'osa_drop_size', 'track_npd', 'npd_drop_size', 'track_mhs', 'mhs_drop_size'] as $column) {
                if (Schema::hasColumn('skus', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
