<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('brands_report_submissions',function(Blueprint $t){$t->id();$t->string('category',40);$t->string('source_type',20);$t->unsignedBigInteger('source_id')->nullable();$t->foreignId('submitted_by')->constrained('users');$t->json('payload');$t->string('source_token',64)->nullable();$t->string('status',20)->default('pending');$t->text('review_note')->nullable();$t->foreignId('reviewed_by')->nullable()->constrained('users');$t->timestamp('reviewed_at')->nullable();$t->unsignedInteger('revision')->default(1);$t->timestamps();$t->index(['status','submitted_by']);});}
 public function down():void {Schema::dropIfExists('brands_report_submissions');}
};
