<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_objectives', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('performance_objectives')->restrictOnDelete();
            $t->string('department')->nullable()->index();
            $t->string('title');
            $t->text('outcome');
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->date('starts_on');
            $t->date('ends_on');
            $t->timestamps();
        });
        Schema::create('performance_kpis', function (Blueprint $t) {
            $t->id();
            $t->foreignId('objective_id')->constrained('performance_objectives')->restrictOnDelete();
            $t->string('department')->index();
            $t->string('name');
            $t->text('definition');
            $t->text('formula');
            $t->string('unit', 50);
            $t->string('direction', 10);
            $t->string('aggregation', 30);
            $t->string('frequency', 20);
            $t->text('source');
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $t->decimal('green_threshold', 8, 2);
            $t->decimal('amber_threshold', 8, 2);
            $t->unsignedInteger('escalation_days');
            $t->text('required_action');
            $t->string('dimension', 40);
            $t->decimal('weight', 8, 2)->default(1);
            $t->boolean('evidence_required')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('performance_observations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kpi_id')->constrained('performance_kpis')->restrictOnDelete();
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->date('period_start');
            $t->date('period_end');
            $t->string('client')->nullable();
            $t->string('project')->nullable();
            $t->string('scope_key', 64)->unique();
            $t->decimal('target', 18, 4);
            $t->decimal('actual', 18, 4)->nullable();
            $t->decimal('numerator', 18, 4)->nullable();
            $t->decimal('denominator', 18, 4)->nullable();
            $t->text('source_reference');
            $t->text('evidence_url')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['kpi_id', 'period_start', 'period_end']);
        });
        Schema::table('weekly_consolidated_items', function (Blueprint $t) {
            $t->foreignId('performance_kpi_id')->nullable()->constrained('performance_kpis')->restrictOnDelete();
            $t->string('workflow_state', 20)->default('legacy')->index();
            $t->string('record_type', 30)->default('deliverable');
            $t->string('team')->nullable();
            $t->unsignedBigInteger('reviewer_id')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->decimal('quality_score', 5, 2)->nullable();
            $t->string('risk_level', 20)->default('low');
            $t->string('root_cause', 50)->nullable();
            $t->text('management_decision')->nullable();
            $t->json('department_data')->nullable();
            $t->unsignedInteger('revision')->default(1);
            $t->unsignedInteger('escalation_level')->default(0);
            $t->timestamp('exception_since')->nullable();
            $t->index(['department', 'lead_staff_id', 'week_end']);
        });
        Schema::create('performance_dependencies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('upstream_id')->constrained('weekly_consolidated_items')->restrictOnDelete();
            $t->foreignId('downstream_id')->constrained('weekly_consolidated_items')->restrictOnDelete();
            $t->text('impact');
            $t->timestamps();
            $t->unique(['upstream_id', 'downstream_id']);
        });
        Schema::create('performance_events', function (Blueprint $t) {
            $t->id();
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id');
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action');
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->text('reason');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['entity_type', 'entity_id']);
        });
        Schema::create('performance_evidence', function (Blueprint $t) {
            $t->id();
            $t->foreignId('commitment_id')->constrained('weekly_consolidated_items')->restrictOnDelete();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->string('path');
            $t->string('original_name');
            $t->string('mime');
            $t->unsignedBigInteger('bytes');
            $t->string('sha256', 64);
            $t->timestamps();
        });
        Schema::create('performance_outbox', function (Blueprint $t) {
            $t->id();
            $t->string('dedupe_key', 64)->unique();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('title');
            $t->text('message');
            $t->string('url');
            $t->timestamp('dashboard_at')->nullable();
            $t->timestamp('emailed_at')->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->text('last_error')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['performance_outbox', 'performance_evidence', 'performance_events', 'performance_dependencies'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('weekly_consolidated_items', function (Blueprint $t) {
            $t->dropIndex(['department', 'lead_staff_id', 'week_end']);
            $t->dropIndex(['workflow_state']);
            $t->dropConstrainedForeignId('performance_kpi_id');
            $t->dropColumn(['workflow_state', 'record_type', 'team', 'reviewer_id', 'accepted_at', 'submitted_at', 'verified_at', 'closed_at', 'quality_score', 'risk_level', 'root_cause', 'management_decision', 'department_data', 'revision', 'escalation_level', 'exception_since']);
        });
        foreach (['performance_observations', 'performance_kpis', 'performance_objectives'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
