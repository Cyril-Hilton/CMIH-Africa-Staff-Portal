<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'payroll_deductions')) {
                $table->decimal('payroll_deductions', 12, 2)->nullable()->after('salary');
            }

            if (! Schema::hasColumn('users', 'payroll_rewards_bonus')) {
                $table->decimal('payroll_rewards_bonus', 12, 2)->nullable()->after('payroll_deductions');
            }

            if (! Schema::hasColumn('users', 'payroll_notes')) {
                $table->text('payroll_notes')->nullable()->after('payroll_rewards_bonus');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['payroll_deductions', 'payroll_rewards_bonus', 'payroll_notes'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
