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
        Schema::create('action_points', function (Blueprint $table) {
            $table->id();
            $table->text('action_point');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignee_name')->nullable();
            $table->string('status')->default('pending'); // pending, in_progress, done, not_done
            $table->text('comments')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_points');
    }
};
