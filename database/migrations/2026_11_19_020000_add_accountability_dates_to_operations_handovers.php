<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::table('operations_handovers',function(Blueprint $table){$table->unsignedBigInteger('operations_register_id')->nullable()->unique();$table->unsignedBigInteger('handover_approved_by')->nullable();$table->dateTime('handover_approved_at')->nullable();$table->dateTime('execution_due_at')->nullable();$table->dateTime('agreed_setup_due_at')->nullable();$table->dateTime('agreed_execution_due_at')->nullable();$table->dateTime('execution_completed_at')->nullable();});}
 public function down(): void {Schema::table('operations_handovers',fn(Blueprint $table)=>$table->dropColumn(['operations_register_id','handover_approved_by','handover_approved_at','execution_due_at','agreed_setup_due_at','agreed_execution_due_at','execution_completed_at']));}
};
