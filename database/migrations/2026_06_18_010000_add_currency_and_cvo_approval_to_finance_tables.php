<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add currency to petty_cash_claims
        Schema::table('petty_cash_claims', function (Blueprint $table) {
            $table->string('currency', 10)->default('GHC')->after('amount');
            // Status now includes: Pending (awaiting CVO), CVO Approved, Paid, Rejected, Flagged
        });

        // Add currency to project_budgets
        Schema::table('project_budgets', function (Blueprint $table) {
            $table->string('currency', 10)->default('GHC')->after('total_amount');
            // Status now includes: Draft, Submitted (awaiting CVO), CVO Approved, Rejected
        });

        // Add currency to supplier_invoices
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->string('currency', 10)->default('GHC')->after('amount');
            // Status now includes: Pending (awaiting CVO), CVO Approved, Paid, Rejected, Flagged
        });

        // Add currency to project_budget_items (for per-line currency support)
        Schema::table('project_budget_items', function (Blueprint $table) {
            $table->string('currency', 10)->default('GHC')->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_claims', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
        Schema::table('project_budgets', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
        Schema::table('project_budget_items', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
