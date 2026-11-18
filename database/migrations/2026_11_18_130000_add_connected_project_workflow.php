<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crm_pipeline_projects', function (Blueprint $table) {
            $table->json('team_workflow')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('crm_pipeline_projects', fn(Blueprint $table)=>$table->dropColumn('team_workflow'));
    }
};
