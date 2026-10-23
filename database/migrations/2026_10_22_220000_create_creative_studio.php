<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('creative_requests',function(Blueprint $t){
   $t->id();$t->uuid('uuid')->unique();$t->string('reference')->unique();$t->foreignId('task_id')->nullable()->unique()->constrained('tasks')->nullOnDelete();
   $t->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();$t->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();$t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
   $t->json('requester_snapshot')->nullable();$t->json('assignee_snapshot')->nullable();$t->string('department')->nullable()->index();$t->string('title');$t->text('brief')->nullable();$t->string('client')->nullable();$t->string('creative_type')->nullable();$t->string('priority')->default('Medium');
   $t->string('stage')->default('brief_received')->index();$t->string('visibility')->default('shared');$t->boolean('legacy')->default(false);
   foreach(['received_at','acknowledged_at','reported_external_at','source_recorded_at','requested_deadline','planned_start','internal_eta','first_submitted_at','submitted_at','review_due_at','reviewed_at','delivered_at','completed_at','archived_at'] as $column)$t->timestamp($column)->nullable()->index();
   $t->decimal('estimated_hours',8,2)->nullable();$t->unsignedInteger('iteration')->default(0);$t->unsignedInteger('revision')->default(1);$t->foreignId('planned_by')->nullable()->constrained('users')->nullOnDelete();$t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();$t->decimal('quality_score',5,2)->nullable();$t->text('blocker')->nullable();$t->string('blocker_owner')->nullable();$t->timestamps();
  });
  Schema::create('creative_sources',function(Blueprint $t){$t->id();$t->foreignId('creative_request_id')->constrained()->cascadeOnDelete();$t->string('source_type');$t->unsignedBigInteger('source_id');$t->unique(['source_type','source_id']);});
  Schema::create('creative_updates',function(Blueprint $t){$t->id();$t->foreignId('creative_request_id')->constrained()->cascadeOnDelete();$t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();$t->string('kind');$t->text('body');$t->text('reference_url')->nullable();$t->json('metadata')->nullable();$t->timestamps();});
  Schema::create('creative_attachments',function(Blueprint $t){$t->id();$t->foreignId('creative_request_id')->constrained()->cascadeOnDelete();$t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();$t->string('kind');$t->string('original_name');$t->string('mime');$t->string('path');$t->unsignedBigInteger('size');$t->unsignedInteger('iteration')->default(0);$t->timestamps();});
  Schema::create('creative_capacities',function(Blueprint $t){$t->id();$t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();$t->decimal('weekly_hours',6,2);$t->foreignId('updated_by')->constrained('users');$t->timestamps();});
  Schema::create('creative_alerts',function(Blueprint $t){$t->id();$t->string('dedupe_key')->unique();$t->foreignId('creative_request_id')->constrained()->cascadeOnDelete();$t->timestamps();});
 }
 public function down(): void {foreach(['creative_alerts','creative_capacities','creative_attachments','creative_updates','creative_sources','creative_requests'] as $table)Schema::dropIfExists($table);}
};
