<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_target_plans', function (Blueprint $table) {
            $table->foreignId('amends_plan_id')->nullable()->constrained('performance_target_plans')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('performance_target_plans', fn (Blueprint $table) => $table->dropConstrainedForeignId('amends_plan_id'));
    }
};
