<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
 public function up():void {
  Schema::table('users',fn(Blueprint $t)=>$t->boolean('is_service_account')->default(false)->index());
  DB::table('users')->where(fn($q)=>$q->whereRaw('LOWER(TRIM(email)) = ?',['cmihstaffs@cmih.africa'])->orWhereRaw('LOWER(TRIM(name)) = ?',['cmih staffs presentation account']))->update(['is_service_account'=>true]);
 }
 public function down():void {Schema::table('users',fn(Blueprint $t)=>$t->dropColumn('is_service_account'));}
};
