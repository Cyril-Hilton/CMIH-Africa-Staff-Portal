<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['crm_pipeline_projects', 'crm_client_records', 'crm_weekly_progress'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->json('supporting_staff_ids')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['crm_pipeline_projects', 'crm_client_records', 'crm_weekly_progress'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('supporting_staff_ids'));
        }
    }
};
