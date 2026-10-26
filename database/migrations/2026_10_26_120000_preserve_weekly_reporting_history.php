<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('weekly_consolidated_items',function(Blueprint $t){
   $t->uuid('report_series')->nullable();$t->date('project_starts_on')->nullable();$t->date('project_ends_on')->nullable();
   $t->unsignedBigInteger('carried_from_id')->nullable();$t->string('report_status')->default('legacy_unreviewed');
   $t->json('previous_week_reference')->nullable();$t->timestamp('report_submitted_at')->nullable();$t->timestamp('report_reviewed_at')->nullable();$t->unsignedBigInteger('report_reviewed_by')->nullable();
   $t->unique(['report_series','week_start'],'weekly_series_period_unique');
  });
  Schema::create('weekly_report_versions',function(Blueprint $t){$t->id();$t->unsignedBigInteger('weekly_item_id')->index();$t->unsignedBigInteger('actor_id')->nullable();$t->string('action');$t->text('reason')->nullable();$t->json('snapshot');$t->timestamp('created_at')->useCurrent();});
 }
 public function down():void {Schema::dropIfExists('weekly_report_versions');Schema::table('weekly_consolidated_items',function(Blueprint $t){$t->dropUnique('weekly_series_period_unique');$t->dropColumn(['report_series','project_starts_on','project_ends_on','carried_from_id','report_status','previous_week_reference','report_submitted_at','report_reviewed_at','report_reviewed_by']);});}
};
