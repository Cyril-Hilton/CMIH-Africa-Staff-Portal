<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_pipeline_projects', function (Blueprint $t) {
            $t->string('import_key', 64)->nullable()->unique();
            $t->string('source_sheet')->nullable();
            $t->unsignedInteger('source_row')->nullable();
            $t->string('source_period_label')->nullable();
            $t->boolean('source_period_confirmed')->default(true);
            $t->decimal('source_forecast', 18, 2)->nullable();
            $t->json('source_values')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crm_pipeline_projects', function (Blueprint $t) {
            $t->dropUnique(['import_key']);
            $t->dropColumn(['import_key', 'source_sheet', 'source_row', 'source_period_label', 'source_period_confirmed', 'source_forecast', 'source_values']);
        });
    }
};
