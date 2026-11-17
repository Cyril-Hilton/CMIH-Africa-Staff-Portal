<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['crm_pipeline_projects', 'crm_weekly_progress'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->date('week_end')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['crm_pipeline_projects', 'crm_weekly_progress'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('week_end'));
        }
    }
};
