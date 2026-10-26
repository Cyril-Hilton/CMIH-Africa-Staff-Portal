<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['crm_pipeline_projects', 'crm_account_targets'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('revenue_state')->default('awaiting_finance');
                $t->unsignedBigInteger('revenue_submitted_by')->nullable();
                $t->unsignedBigInteger('revenue_finance_by')->nullable();
                $t->decimal('revenue_proposed_amount', 14, 2)->nullable();
                $t->text('revenue_review_note')->nullable();
                $t->timestamp('revenue_submitted_at')->nullable();
                $t->timestamp('revenue_confirmed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['crm_pipeline_projects', 'crm_account_targets'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['revenue_state', 'revenue_submitted_by', 'revenue_finance_by', 'revenue_proposed_amount', 'revenue_review_note', 'revenue_submitted_at', 'revenue_confirmed_at']));
        }
    }
};
