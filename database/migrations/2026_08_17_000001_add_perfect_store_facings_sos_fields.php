<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            if (! Schema::hasColumn('skus', 'facing_target')) {
                $table->unsignedInteger('facing_target')->default(1)->after('mhs_drop_size');
            }

            if (! Schema::hasColumn('skus', 'track_planogram')) {
                $table->boolean('track_planogram')->default(true)->after('facing_target');
            }

            if (! Schema::hasColumn('skus', 'sos_target')) {
                $table->decimal('sos_target', 5, 2)->nullable()->after('track_planogram');
            }
        });

        Schema::table('merchandiser_visit_skus', function (Blueprint $table) {
            if (! Schema::hasColumn('merchandiser_visit_skus', 'facing_target_snapshot')) {
                $table->unsignedInteger('facing_target_snapshot')->nullable()->after('facing');
            }

            if (! Schema::hasColumn('merchandiser_visit_skus', 'category_unilever_facings')) {
                $table->unsignedInteger('category_unilever_facings')->nullable()->after('share_of_shelf');
            }

            if (! Schema::hasColumn('merchandiser_visit_skus', 'category_total_facings')) {
                $table->unsignedInteger('category_total_facings')->nullable()->after('category_unilever_facings');
            }

            if (! Schema::hasColumn('merchandiser_visit_skus', 'shelf_price')) {
                $table->decimal('shelf_price', 12, 2)->nullable()->after('category_total_facings');
            }

            if (! Schema::hasColumn('merchandiser_visit_skus', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('shelf_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merchandiser_visit_skus', function (Blueprint $table) {
            foreach (['facing_target_snapshot', 'category_unilever_facings', 'category_total_facings', 'shelf_price', 'photo_path'] as $column) {
                if (Schema::hasColumn('merchandiser_visit_skus', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('skus', function (Blueprint $table) {
            foreach (['facing_target', 'track_planogram', 'sos_target'] as $column) {
                if (Schema::hasColumn('skus', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
