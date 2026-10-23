<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('operations_handovers',function(Blueprint $t){
  $t->id();$t->foreignId('project_id')->constrained('crm_pipeline_projects')->restrictOnDelete();$t->foreignId('requester_id')->constrained('users')->restrictOnDelete();$t->foreignId('lead_id')->nullable()->constrained('users')->restrictOnDelete();$t->foreignId('task_id')->nullable()->constrained('tasks')->restrictOnDelete();
  $t->string('title');$t->text('plan');$t->text('acceptance_criteria');$t->text('plan_url')->nullable();$t->string('plan_path')->nullable();$t->string('plan_name')->nullable();$t->dateTime('event_at');$t->dateTime('setup_due_at');$t->string('location');$t->string('priority',20);$t->string('stage',30)->default('submitted');
  $t->dateTime('acknowledged_at')->nullable();$t->dateTime('meeting_scheduled_at')->nullable();$t->dateTime('meeting_held_at')->nullable();$t->string('meeting_location')->nullable();$t->json('attendees')->nullable();$t->text('meeting_minutes')->nullable();$t->dateTime('cr_signoff_at')->nullable();$t->dateTime('ops_signoff_at')->nullable();
  $t->json('resources')->nullable();$t->text('submission_evidence')->nullable();$t->dateTime('submitted_at')->nullable();$t->dateTime('accepted_at')->nullable();$t->dateTime('verified_at')->nullable();$t->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();$t->decimal('quality_score',5,2)->nullable();$t->unsignedInteger('revision')->default(1);$t->timestamps();$t->index(['stage','setup_due_at']);
 });}
 public function down():void{Schema::dropIfExists('operations_handovers');}
};
