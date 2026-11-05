<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_dashboard_activities', function (Blueprint $table) {
            $table->id();
            $table->string('tab', 24);
            $table->string('category');
            $table->string('activity');
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('department', 64)->nullable();
            $table->date('opened_on');
            $table->date('deadline')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24);
            $table->text('remarks')->nullable();
            $table->json('attachment')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->index(['tab', 'opened_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_dashboard_activities');
    }
};
