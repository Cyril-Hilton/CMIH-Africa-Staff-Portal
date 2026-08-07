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
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('leave_type', 32); // 'annual', 'sick', 'casual', etc.
            $table->foreignId('line_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('covering_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('pending_manager'); // 'pending_manager', 'pending_hr', 'approved', 'rejected'
            $table->text('comments')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
    }
};
