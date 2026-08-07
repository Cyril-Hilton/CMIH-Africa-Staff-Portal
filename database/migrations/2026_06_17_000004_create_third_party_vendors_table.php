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
        Schema::create('third_party_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 32)->default('Projects'); // 'Projects', 'Office'
            $table->foreignId('assigned_project_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->string('deliverable_status', 16)->default('pending'); // 'pending', 'done'
            $table->text('performance_review_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('third_party_vendors');
    }
};
