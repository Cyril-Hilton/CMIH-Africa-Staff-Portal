<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_pipeline_projects', function (Blueprint $t) {
            $t->id();
            $t->string('client_name');
            $t->string('brand')->nullable();
            $t->string('project_name');
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->string('stage', 40);
            $t->date('expected_close_date')->nullable();
            foreach (['proposed_amount', 'po_amount', 'target_amount', 'actual_amount', 'invoiced_amount', 'collected_amount'] as $field) {
                $t->decimal($field, 18, 2)->nullable();
            }
            $t->text('bottlenecks')->nullable();
            $t->text('next_action')->nullable();
            $t->string('public_action', 50)->nullable();
            $t->string('contact_name')->nullable();
            $t->string('contact_position')->nullable();
            $t->string('contact_info')->nullable();
            $t->text('invoice_status')->nullable();
            $t->string('po_status')->nullable();
            $t->text('remarks')->nullable();
            $t->date('week_start');
            $t->string('source_reference')->nullable();
            $t->unsignedInteger('revision')->default(1);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['owner_id', 'week_start']);
        });
        Schema::create('crm_weekly_progress', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained('crm_pipeline_projects')->restrictOnDelete();
            $t->string('client_name');
            $t->string('project_name');
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->date('week_start');
            $t->string('stage', 40);
            $t->decimal('stage_percent', 8, 2)->nullable();
            foreach (['achievement_percent', 'po_percent', 'billing_percent', 'collection_percent'] as $field) {
                $t->decimal($field, 10, 2)->nullable();
            }
            $t->string('public_action', 50)->nullable();
            $t->date('expected_close_date')->nullable();
            $t->timestamps();
            $t->unique(['project_id', 'week_start']);
        });
        Schema::create('crm_account_targets', function (Blueprint $t) {
            $t->id();
            $t->string('client_name');
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->date('period_start');
            $t->date('period_end');
            $t->decimal('target_amount', 18, 2);
            $t->string('reference')->unique();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_account_targets');
        Schema::dropIfExists('crm_weekly_progress');
        Schema::dropIfExists('crm_pipeline_projects');
    }
};
