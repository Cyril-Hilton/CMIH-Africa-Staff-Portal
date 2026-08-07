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
        Schema::create('performance_awards', function (Blueprint $table) {
            $table->id();
            $table->string('award_type', 32); // 'employee_of_the_month', 'department_of_the_month', 'employee_of_the_year', 'department_of_the_year'
            $table->string('period', 16);     // 'YYYY-MM' (for month) or 'YYYY' (for year)
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('first_runner_up_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('second_runner_up_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('winner_val')->nullable();           // e.g., department key like 'creatives' (for department awards)
            $table->string('first_runner_up_val')->nullable();  // e.g., 'finance'
            $table->string('second_runner_up_val')->nullable(); // e.g., 'hr_admin'
            $table->decimal('winner_score', 5, 2)->nullable();
            $table->decimal('first_runner_up_score', 5, 2)->nullable();
            $table->decimal('second_runner_up_score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['award_type', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_awards');
    }
};
