<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_execution_controls', function (Blueprint $t) {
            $t->id();
            $t->foreignId('crm_project_id')->unique()->constrained('crm_pipeline_projects')->restrictOnDelete();
            $t->json('data');
            $t->unsignedInteger('revision')->default(1);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('executive_tracker_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->json('data');
            $t->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('executive_reminder_logs', function (Blueprint $t) {
            $t->id();
            $t->string('reminder_key', 64)->unique();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('source_key');
            $t->dateTime('due_at');
            $t->string('window', 20);
            $t->foreignId('outbox_id')->nullable()->constrained('performance_outbox')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_reminder_logs');
        Schema::dropIfExists('executive_tracker_settings');
        Schema::dropIfExists('project_execution_controls');
    }
};
