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
        // Finance: Petty Cash/Reimbursement Claims
        Schema::create('petty_cash_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->text('description')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('status', 16)->default('Pending'); // 'Pending', 'Approved', 'Flagged', 'Paid'
            $table->timestamps();
        });

        // Operations: Freelance Promoters Directory
        Schema::create('freelance_promoters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact');
            $table->string('city');
            $table->string('language')->nullable();
            $table->string('tshirt_size', 8)->nullable();
            $table->string('height', 16)->nullable();
            $table->timestamps();
        });

        // HR & Admin: Appraisal Metrics for form builder
        Schema::create('appraisal_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('General'); // e.g. General, Technical, Leadership
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appraisal_metrics');
        Schema::dropIfExists('freelance_promoters');
        Schema::dropIfExists('petty_cash_claims');
    }
};
