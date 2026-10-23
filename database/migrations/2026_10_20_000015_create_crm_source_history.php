<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_source_history', function (Blueprint $t) {
            $t->id();
            $t->string('source_id');
            $t->string('sheet');
            $t->unsignedInteger('source_row');
            $t->boolean('hidden_sheet');
            $t->json('cells');
            $t->json('formulas');
            $t->string('checksum', 64);
            $t->timestamp('imported_at');
            $t->unique(['source_id', 'sheet', 'source_row'], 'crm_source_row');
        });
        Schema::table('crm_account_targets', function (Blueprint $t) {
            $t->string('frequency', 20)->default('custom');
            $t->decimal('actual_amount', 18, 2)->nullable();
            $t->string('source_reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crm_account_targets', fn (Blueprint $t) => $t->dropColumn(['frequency', 'actual_amount', 'source_reference']));
        Schema::dropIfExists('crm_source_history');
    }
};
