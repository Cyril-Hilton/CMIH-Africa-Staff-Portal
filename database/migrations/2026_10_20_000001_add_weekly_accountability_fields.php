<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_consolidated_items', function (Blueprint $table) {
            $table->string('objective', 500)->nullable();
            $table->string('kpi_name')->nullable();
            $table->string('measurement_unit', 50)->nullable();
            $table->string('measurement_direction', 10)->default('higher');
            $table->decimal('target_value', 18, 4)->nullable();
            $table->decimal('actual_value', 18, 4)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('evidence_url')->nullable();
            $table->text('issue_impact')->nullable();
            $table->text('corrective_action')->nullable();
            $table->foreignId('action_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('action_due_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('weekly_consolidated_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('action_owner_id');
            $table->dropColumn(['objective', 'kpi_name', 'measurement_unit', 'measurement_direction', 'target_value', 'actual_value', 'completed_at', 'evidence_url', 'issue_impact', 'corrective_action', 'action_due_date']);
        });
    }
};
