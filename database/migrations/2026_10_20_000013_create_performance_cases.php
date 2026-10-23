<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_cases', function (Blueprint $t) {
            $t->id();
            $t->string('department');
            $t->string('kind', 20);
            $t->string('title');
            $t->text('description');
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('performance_cases')->restrictOnDelete();
            $t->foreignId('commitment_id')->nullable()->constrained('weekly_consolidated_items')->restrictOnDelete();
            $t->foreignId('kpi_id')->nullable()->constrained('performance_kpis')->restrictOnDelete();
            $t->string('root_cause')->nullable();
            $t->text('business_impact');
            $t->date('due_on');
            $t->date('review_on')->nullable();
            $t->string('priority', 20);
            $t->string('status', 20)->default('open');
            $t->text('resolution')->nullable();
            $t->text('evidence_url')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->unsignedInteger('revision')->default(1);
            $t->timestamps();
            $t->index(['department', 'status', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_cases');
    }
};
