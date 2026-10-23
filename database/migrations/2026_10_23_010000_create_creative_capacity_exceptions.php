<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('creative_capacity_exceptions',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained();$t->date('starts_on');$t->date('ends_on');$t->decimal('weekly_hours',6,2);$t->foreignId('updated_by')->constrained('users');$t->text('reason');$t->timestamps();$t->index(['user_id','starts_on','ends_on']);});}
 public function down():void{Schema::dropIfExists('creative_capacity_exceptions');}
};
