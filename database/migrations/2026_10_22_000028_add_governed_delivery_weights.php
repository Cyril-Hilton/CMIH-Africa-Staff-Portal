<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        foreach (['tasks','weekly_consolidated_items'] as $table) {
            Schema::table($table,function(Blueprint $t){
                $t->unsignedSmallInteger('performance_weight')->default(1);
                $t->unsignedBigInteger('weight_approved_by')->nullable();
                $t->timestamp('weight_approved_at')->nullable();
                $t->text('weight_reason')->nullable();
            });
        }
    }
    public function down(): void {
        foreach (['tasks','weekly_consolidated_items'] as $table) Schema::table($table,fn(Blueprint $t)=>$t->dropColumn(['performance_weight','weight_approved_by','weight_approved_at','weight_reason']));
    }
};
