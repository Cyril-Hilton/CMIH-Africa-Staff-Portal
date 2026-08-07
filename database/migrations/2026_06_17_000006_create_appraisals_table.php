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
        Schema::create('appraisals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('quarter', 4); // 'Q1', 'Q2', 'Q3', 'Q4'
            $table->integer('year');
            $table->json('self_assessment')->nullable();
            $table->json('manager_review')->nullable();
            $table->string('status', 32)->default('draft'); // 'draft', 'submitted', 'manager_reviewed', 'approved'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appraisals');
    }
};
