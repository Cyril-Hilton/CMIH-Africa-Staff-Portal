<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void { Schema::table('vendor_printing_requests',function(Blueprint $t){
        $t->string('review_status')->default('legacy')->index();
        $t->unsignedBigInteger('reviewed_by')->nullable();$t->timestamp('reviewed_at')->nullable();
        $t->unsignedInteger('reviewed_revision')->nullable();$t->text('review_note')->nullable();
    }); }
    public function down():void {Schema::table('vendor_printing_requests',fn(Blueprint $t)=>$t->dropColumn(['review_status','reviewed_by','reviewed_at','reviewed_revision','review_note']));}
};
