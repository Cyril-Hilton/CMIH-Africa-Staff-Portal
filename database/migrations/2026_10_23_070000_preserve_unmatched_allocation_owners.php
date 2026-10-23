<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void{Schema::table('crm_account_allocations',function(Blueprint $t){$t->unsignedBigInteger('owner_id')->nullable()->change();$t->string('reported_owner')->nullable();});}public function down():void{Schema::table('crm_account_allocations',function(Blueprint $t){$t->dropColumn('reported_owner');});}};
