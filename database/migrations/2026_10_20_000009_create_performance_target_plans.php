<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_target_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kpi_id')->constrained('performance_kpis')->restrictOnDelete();
            $t->string('department')->index();
            $t->string('scope_type', 20);
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->date('period_start');
            $t->date('period_end');
            $t->string('client')->nullable();
            $t->string('project')->nullable();
            $t->decimal('target', 18, 4);
            $t->decimal('weight', 8, 2);
            $t->text('rationale');
            $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->text('review_notes')->nullable();
            $t->timestamp('withdrawn_at')->nullable();
            $t->foreignId('withdrawn_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('withdrawal_reason')->nullable();
            $t->string('scope_key', 64)->unique();
            $t->timestamps();
            $t->index(['owner_id', 'period_start', 'period_end']);
        });
        Schema::table('performance_observations', function (Blueprint $t) {
            $t->foreignId('target_plan_id')->nullable()->unique()->constrained('performance_target_plans')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('performance_observations', function (Blueprint $t) {
            $t->dropUnique(['target_plan_id']);
            $t->dropConstrainedForeignId('target_plan_id');
        });
        Schema::dropIfExists('performance_target_plans');
    }
};
