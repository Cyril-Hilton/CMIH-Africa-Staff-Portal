<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_consolidated_items', function (Blueprint $t) {
            $t->foreignId('source_task_id')->nullable()->unique()->constrained('tasks')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('weekly_consolidated_items', function (Blueprint $t) {
            $t->dropUnique(['source_task_id']);
            $t->dropConstrainedForeignId('source_task_id');
        });
    }
};
