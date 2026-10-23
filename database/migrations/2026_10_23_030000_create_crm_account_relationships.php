<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void { Schema::create('crm_account_relationships',function(Blueprint $t){
  $t->id();$t->string('client_name');$t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
  $t->string('relationship',20);$t->date('starts_on')->nullable();$t->date('ends_on')->nullable();$t->text('note')->nullable();
  $t->foreignId('created_by')->constrained('users')->restrictOnDelete();$t->timestamps();$t->index(['client_name','owner_id']);
 }); }
 public function down():void {Schema::dropIfExists('crm_account_relationships');}
};
