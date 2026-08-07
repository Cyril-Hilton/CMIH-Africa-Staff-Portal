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
        Schema::table('project_budgets', function (Blueprint $table) {
            $table->longText('content')->nullable()->after('notes');
        });

        Schema::create('budget_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('project_budgets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('permission', 10)->default('view'); // 'view' or 'edit'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_collaborators');

        Schema::table('project_budgets', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
