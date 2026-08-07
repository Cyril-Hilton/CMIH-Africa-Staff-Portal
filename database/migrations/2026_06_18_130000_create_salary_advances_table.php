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
        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->string('repayment_style', 32); // 'monthly_deduction' or 'pay_all_at_once'
            $table->decimal('monthly_deduction_amount', 12, 2)->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 32)->default('pending_finance'); // 'pending_finance', 'returned_for_correction', 'pending_cvo', 'approved', 'rejected'
            $table->text('finance_feedback')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_advances');
    }
};
