<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['crm_pipeline_projects', 'crm_weekly_progress'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                foreach (['description', 'shared_bottlenecks', 'shared_next_action', 'comments'] as $field) {
                    $t->text($field)->nullable();
                }
            });
        }
        Schema::table('crm_weekly_progress', function (Blueprint $t) {
            $t->boolean('has_proposed_amount')->default(false);
            $t->boolean('has_po_amount')->default(false);
            $t->boolean('has_weighted_forecast')->default(false);
            $t->string('po_state')->default('unknown');
        });
    }

    public function down(): void
    {
        foreach (['crm_pipeline_projects', 'crm_weekly_progress'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['description', 'shared_bottlenecks', 'shared_next_action', 'comments']));
        }
        Schema::table('crm_weekly_progress', fn (Blueprint $t) => $t->dropColumn(['has_proposed_amount', 'has_po_amount', 'has_weighted_forecast', 'po_state']));
    }
};
