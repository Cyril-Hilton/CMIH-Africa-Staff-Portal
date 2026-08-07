<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds two columns to appraisal_metrics:
     *  - metric_type: 'slider' (default 1–10 score) | 'table' (row/col grid entry)
     *  - table_template: JSON defining columns e.g.
     *      [
     *        {"key":"objective","label":"Objective / KPI","type":"text","width":"30%"},
     *        {"key":"weight",   "label":"Weight (%)",     "type":"number","width":"10%"},
     *        {"key":"target",   "label":"Target",         "type":"text","width":"20%"},
     *        {"key":"actual",   "label":"Actual",         "type":"text","width":"20%"},
     *        {"key":"score",    "label":"Score (1–10)",   "type":"score","width":"10%"},
     *        {"key":"remarks",  "label":"Remarks",        "type":"textarea","width":"10%"}
     *      ]
     *  - default_rows: integer default number of empty rows shown in the table
     */
    public function up(): void
    {
        Schema::table('appraisal_metrics', function (Blueprint $table) {
            $table->string('metric_type', 16)->default('slider')->after('category'); // 'slider' | 'table'
            $table->json('table_template')->nullable()->after('metric_type');        // column definitions
            $table->unsignedTinyInteger('default_rows')->default(3)->after('table_template'); // how many rows to show
        });

        // Also add table_data column to appraisals to store table-structured responses
        Schema::table('appraisals', function (Blueprint $table) {
            $table->json('self_table_data')->nullable()->after('self_assessment');
            $table->json('manager_table_data')->nullable()->after('manager_review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appraisal_metrics', function (Blueprint $table) {
            $table->dropColumn(['metric_type', 'table_template', 'default_rows']);
        });
        Schema::table('appraisals', function (Blueprint $table) {
            $table->dropColumn(['self_table_data', 'manager_table_data']);
        });
    }
};
