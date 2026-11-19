<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::table('asset_warehouse_requests',function(Blueprint $table){
  $table->foreignId('custodian_id')->nullable()->constrained('users')->nullOnDelete();
  $table->foreignId('crm_project_id')->nullable()->constrained('crm_pipeline_projects')->nullOnDelete();
  $table->foreignId('operations_handover_id')->nullable()->constrained('operations_handovers')->nullOnDelete();
  $table->foreignId('operations_register_id')->nullable()->constrained('performance_registers')->nullOnDelete();
  $table->unique(['operations_register_id','asset_id'],'warehouse_report_asset_unique');
 });}
 public function down():void{Schema::table('asset_warehouse_requests',function(Blueprint $table){$table->dropUnique('warehouse_report_asset_unique');foreach(['custodian_id','crm_project_id','operations_handover_id','operations_register_id'] as $field)$table->dropConstrainedForeignId($field);});}
};
