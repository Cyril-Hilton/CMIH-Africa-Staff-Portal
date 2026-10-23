<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('crm_financial_register',function(Blueprint $t){$t->id();$t->string('import_key',64)->unique();$t->string('client_name');$t->string('project_name');$t->foreignId('owner_id')->constrained('users')->restrictOnDelete();$t->decimal('po_amount',18,2)->nullable();$t->decimal('po_balance',18,2)->nullable();$t->decimal('invoiced_amount',18,2)->nullable();$t->string('po_status')->nullable();$t->string('invoice_status')->nullable();$t->string('source_sheet');$t->unsignedInteger('source_row');$t->unsignedSmallInteger('source_year')->nullable();$t->date('as_of')->nullable();$t->json('source_values');$t->string('source_checksum',64);$t->timestamps();});}
 public function down():void{Schema::dropIfExists('crm_financial_register');}
};
