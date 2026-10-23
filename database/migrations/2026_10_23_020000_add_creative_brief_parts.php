<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::table('creative_requests',function(Blueprint $t){$t->foreignId('parent_request_id')->nullable()->constrained('creative_requests')->restrictOnDelete();$t->boolean('is_group')->default(false);});}
 public function down():void{Schema::table('creative_requests',function(Blueprint $t){$t->dropForeign(['parent_request_id']);$t->dropColumn(['parent_request_id','is_group']);});}
};
