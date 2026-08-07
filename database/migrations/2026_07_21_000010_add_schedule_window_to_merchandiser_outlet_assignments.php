<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('merchandiser_outlet_assignments')) {
            return;
        }

        Schema::table('merchandiser_outlet_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('merchandiser_outlet_assignments', 'assigned_start_at')) {
                $table->timestamp('assigned_start_at')->nullable()->after('assigned_date');
            }

            if (! Schema::hasColumn('merchandiser_outlet_assignments', 'assigned_end_at')) {
                $table->timestamp('assigned_end_at')->nullable()->after('assigned_start_at');
            }
        });

        Schema::table('merchandiser_outlet_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('merchandiser_outlet_assignments', 'assigned_start_at')
                && Schema::hasColumn('merchandiser_outlet_assignments', 'assigned_end_at')) {
                $table->index(['assigned_start_at', 'assigned_end_at'], 'merch_assignment_window_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('merchandiser_outlet_assignments')) {
            return;
        }

        Schema::table('merchandiser_outlet_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('merchandiser_outlet_assignments', 'assigned_start_at')
                && Schema::hasColumn('merchandiser_outlet_assignments', 'assigned_end_at')) {
                $table->dropIndex('merch_assignment_window_index');
            }

            foreach (['assigned_start_at', 'assigned_end_at'] as $column) {
                if (Schema::hasColumn('merchandiser_outlet_assignments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
