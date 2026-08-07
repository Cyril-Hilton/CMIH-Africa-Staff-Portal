<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('period'); // e.g. 2026-07
            $table->decimal('gross_salary', 12, 2)->default(0);
            $table->decimal('ssnit_employee', 12, 2)->default(0);
            $table->decimal('ssnit_employer', 12, 2)->default(0);
            $table->decimal('paye_tax', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('bonuses', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('momo_number')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
