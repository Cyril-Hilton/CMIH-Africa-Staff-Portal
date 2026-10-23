<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_pipeline_projects', function (Blueprint $t) {
            $t->boolean('new_business')->default(false);
            $t->decimal('rate_card_value', 18, 2)->nullable();
            $t->decimal('execution_value', 18, 2)->nullable();
            $t->string('po_state', 20)->default('unknown');
        });
        Schema::create('crm_client_records', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 20);
            $t->string('reference');
            $t->foreignId('project_id')->nullable()->constrained('crm_pipeline_projects')->restrictOnDelete();
            $t->string('client_name');
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->string('status', 30);
            $t->json('data');
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('revision')->default(1);
            $t->timestamps();
            $t->unique(['kind', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_client_records');
        Schema::table('crm_pipeline_projects', function (Blueprint $t) {
            $t->dropColumn(['new_business', 'rate_card_value', 'execution_value', 'po_state']);
        });
    }
};
