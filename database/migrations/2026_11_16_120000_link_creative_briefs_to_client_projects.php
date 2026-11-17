<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_requests', function (Blueprint $table) {
            $table->foreignId('crm_project_id')->nullable()->constrained('crm_pipeline_projects')->nullOnDelete();
            $table->index('crm_project_id');
        });
    }

    public function down(): void
    {
        Schema::table('creative_requests', function (Blueprint $table) {
            $table->dropIndex(['crm_project_id']);
            $table->dropConstrainedForeignId('crm_project_id');
        });
    }
};
