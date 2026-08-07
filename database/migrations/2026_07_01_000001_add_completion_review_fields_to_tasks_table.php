<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('completion_review_status', 32)->nullable()->after('progress');
            $table->timestamp('completion_review_requested_at')->nullable()->after('completion_review_status');
            $table->timestamp('completion_reviewed_at')->nullable()->after('completion_review_requested_at');
            $table->foreignId('completion_reviewed_by')->nullable()->after('completion_reviewed_at')->constrained('users')->nullOnDelete();
            $table->foreignId('completion_review_task_id')->nullable()->after('completion_reviewed_by')->constrained('tasks')->nullOnDelete();
            $table->text('completion_review_note')->nullable()->after('completion_review_task_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['completion_reviewed_by']);
            $table->dropForeign(['completion_review_task_id']);
            $table->dropColumn([
                'completion_review_status',
                'completion_review_requested_at',
                'completion_reviewed_at',
                'completion_reviewed_by',
                'completion_review_task_id',
                'completion_review_note',
            ]);
        });
    }
};
