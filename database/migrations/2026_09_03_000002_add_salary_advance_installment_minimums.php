<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'salary_advance_min_monthly_deduction')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('salary_advance_min_monthly_deduction', 12, 2)
                    ->nullable()
                    ->after('salary')
                    ->comment('Optional HR-approved monthly salary advance deduction minimum for this staff member.');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'salary_advance_min_monthly_deduction')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('salary_advance_min_monthly_deduction');
            });
        }
    }
};
