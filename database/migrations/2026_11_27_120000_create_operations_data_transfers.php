<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('operations_data_transfers',function(Blueprint $t){$t->id();$t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();$t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();$t->string('type',16);$t->string('state',20)->index();$t->string('format',8)->nullable();$t->string('fingerprint',64)->nullable();$t->json('payload');$t->unsignedInteger('revision')->default(1);$t->text('review_note')->nullable();$t->timestamp('reviewed_at')->nullable();$t->timestamps();$t->unique(['submitted_by','type','fingerprint'],'ops_transfer_import_unique');});}
 public function down():void {Schema::dropIfExists('operations_data_transfers');}
};
