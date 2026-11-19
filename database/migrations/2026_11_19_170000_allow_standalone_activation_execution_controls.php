<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_execution_controls', function (Blueprint $t) {
            $t->foreignId('crm_project_id')->nullable()->change();
            $t->foreignId('operations_activation_id')->nullable()->unique()->constrained('performance_registers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('project_execution_controls')->whereNotNull('operations_activation_id')->exists()) {
            throw new RuntimeException('Standalone execution records exist; retain them or restore a verified backup.');
        }Schema::table('project_execution_controls', function (Blueprint $t) {
            $t->dropConstrainedForeignId('operations_activation_id');
            $t->foreignId('crm_project_id')->nullable(false)->change();
        });
    }
};
