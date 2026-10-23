<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{public function up():void{foreach(['crm_financial_register','crm_account_allocations'] as $name)Schema::table($name,fn(Blueprint $t)=>$t->unsignedInteger('revision')->default(1));}public function down():void{foreach(['crm_financial_register','crm_account_allocations'] as $name)Schema::table($name,fn(Blueprint $t)=>$t->dropColumn('revision'));}};
