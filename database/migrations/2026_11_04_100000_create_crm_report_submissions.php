<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('crm_report_submissions', function(Blueprint $t) {
  $t->id(); $t->string('entity_type',20); $t->unsignedBigInteger('entity_id')->nullable();
  $t->foreignId('submitted_by')->constrained('users'); $t->json('payload');
  $t->string('status',20)->default('pending'); $t->text('review_note')->nullable();
  $t->foreignId('reviewed_by')->nullable()->constrained('users'); $t->timestamp('reviewed_at')->nullable();
  $t->unsignedInteger('revision')->default(1); $t->timestamps(); $t->index(['status','submitted_by']);
 }); }
 public function down(): void { Schema::dropIfExists('crm_report_submissions'); }
};
