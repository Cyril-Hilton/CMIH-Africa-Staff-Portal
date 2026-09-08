<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'salary_advance_max_amount')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('salary_advance_max_amount', 12, 2)
                    ->nullable()
                    ->after('salary_advance_min_monthly_deduction')
                    ->comment('Optional HR-approved salary advance maximum amount for this staff member.');
            });
        }

        Schema::table('salary_advances', function (Blueprint $table) {
            if (! Schema::hasColumn('salary_advances', 'hr_reviewed_by')) {
                $table->foreignId('hr_reviewed_by')->nullable()->after('finance_feedback')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('salary_advances', 'hr_reviewed_at')) {
                $table->timestamp('hr_reviewed_at')->nullable()->after('hr_reviewed_by');
            }

            if (! Schema::hasColumn('salary_advances', 'hr_feedback')) {
                $table->text('hr_feedback')->nullable()->after('hr_reviewed_at');
            }

            if (! Schema::hasColumn('salary_advances', 'finance_reviewed_by')) {
                $table->foreignId('finance_reviewed_by')->nullable()->after('hr_feedback')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('salary_advances', 'finance_reviewed_at')) {
                $table->timestamp('finance_reviewed_at')->nullable()->after('finance_reviewed_by');
            }

            if (! Schema::hasColumn('salary_advances', 'cvo_reviewed_by')) {
                $table->foreignId('cvo_reviewed_by')->nullable()->after('finance_reviewed_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('salary_advances', 'cvo_reviewed_at')) {
                $table->timestamp('cvo_reviewed_at')->nullable()->after('cvo_reviewed_by');
            }

            if (! Schema::hasColumn('salary_advances', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('cvo_reviewed_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('salary_advances', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (! Schema::hasColumn('salary_advances', 'approved_monthly_deduction_amount')) {
                $table->decimal('approved_monthly_deduction_amount', 12, 2)->nullable()->after('approved_at');
            }

            if (! Schema::hasColumn('salary_advances', 'repayment_start_date')) {
                $table->date('repayment_start_date')->nullable()->after('approved_monthly_deduction_amount');
            }

            if (! Schema::hasColumn('salary_advances', 'repayment_months')) {
                $table->unsignedInteger('repayment_months')->nullable()->after('repayment_start_date');
            }

            if (! Schema::hasColumn('salary_advances', 'disbursed_by')) {
                $table->foreignId('disbursed_by')->nullable()->after('repayment_months')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('salary_advances', 'disbursed_at')) {
                $table->timestamp('disbursed_at')->nullable()->after('disbursed_by');
            }

            if (! Schema::hasColumn('salary_advances', 'disbursed_amount')) {
                $table->decimal('disbursed_amount', 12, 2)->nullable()->after('disbursed_at');
            }

            if (! Schema::hasColumn('salary_advances', 'fully_paid_at')) {
                $table->timestamp('fully_paid_at')->nullable()->after('disbursed_amount');
            }
        });

        if (! Schema::hasTable('salary_advance_repayments')) {
            Schema::create('salary_advance_repayments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('salary_advance_id')->constrained('salary_advances')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->date('payment_date');
                $table->string('payment_method')->nullable();
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_advance_repayments');

        if (Schema::hasColumn('users', 'salary_advance_max_amount')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('salary_advance_max_amount');
            });
        }

        Schema::table('salary_advances', function (Blueprint $table) {
            $foreignIds = [
                'hr_reviewed_by',
                'finance_reviewed_by',
                'cvo_reviewed_by',
                'approved_by',
                'disbursed_by',
            ];

            foreach ($foreignIds as $column) {
                if (Schema::hasColumn('salary_advances', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            $columns = [
                'hr_reviewed_at',
                'hr_feedback',
                'finance_reviewed_at',
                'cvo_reviewed_at',
                'approved_at',
                'approved_monthly_deduction_amount',
                'repayment_start_date',
                'repayment_months',
                'disbursed_at',
                'disbursed_amount',
                'fully_paid_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('salary_advances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
