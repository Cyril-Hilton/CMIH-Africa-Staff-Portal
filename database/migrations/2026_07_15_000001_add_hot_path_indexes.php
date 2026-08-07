<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->index(['assigned_to', 'created_at'], 'tasks_assigned_created_idx');
                $table->index(['assigned_by', 'created_at'], 'tasks_assigner_created_idx');
                $table->index(['department', 'created_at'], 'tasks_department_created_idx');
                $table->index(['completion_review_status', 'created_at'], 'tasks_review_status_created_idx');
                $table->index(['completion_reviewed_by', 'completion_reviewed_at'], 'tasks_reviewed_by_at_idx');
                $table->index(['due_on', 'status'], 'tasks_due_status_idx');
            });
        }

        if (Schema::hasTable('attendance')) {
            Schema::table('attendance', function (Blueprint $table) {
                $table->index(['user_id', 'clock_in_at'], 'attendance_user_clock_in_idx');
                $table->index(['clock_in_at'], 'attendance_clock_in_idx');
                $table->index(['user_id', 'status'], 'attendance_user_status_idx');
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['user_id', 'read_at', 'created_at'], 'notifications_user_read_created_idx');
            });
        }

        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->index(['conversation_id', 'created_at'], 'messages_conversation_created_idx');
                $table->index(['user_id', 'created_at'], 'messages_user_created_idx');
                $table->index(['is_deleted', 'created_at'], 'messages_deleted_created_idx');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['status', 'department'], 'users_status_department_idx');
                $table->index(['access_role', 'status'], 'users_role_status_idx');
                $table->index(['line_manager_id'], 'users_line_manager_idx');
                $table->index(['last_seen_at'], 'users_last_seen_idx');
            });
        }

        if (Schema::hasTable('weekly_consolidated_items')) {
            Schema::table('weekly_consolidated_items', function (Blueprint $table) {
                $table->index(['department', 'week_start'], 'weekly_dept_week_idx');
                $table->index(['lead_staff_id', 'week_start'], 'weekly_lead_week_idx');
                $table->index(['created_by', 'week_start'], 'weekly_creator_week_idx');
                $table->index(['status', 'week_start'], 'weekly_status_week_idx');
            });
        }

        if (Schema::hasTable('dashboard_columns')) {
            Schema::table('dashboard_columns', function (Blueprint $table) {
                $table->index(['department', 'order'], 'dashboard_columns_dept_order_idx');
            });
        }
    }

    public function down(): void
    {
        $this->dropIndex('dashboard_columns', 'dashboard_columns_dept_order_idx');

        foreach ([
            'weekly_dept_week_idx',
            'weekly_lead_week_idx',
            'weekly_creator_week_idx',
            'weekly_status_week_idx',
        ] as $index) {
            $this->dropIndex('weekly_consolidated_items', $index);
        }

        foreach ([
            'users_status_department_idx',
            'users_role_status_idx',
            'users_line_manager_idx',
            'users_last_seen_idx',
        ] as $index) {
            $this->dropIndex('users', $index);
        }

        foreach ([
            'messages_conversation_created_idx',
            'messages_user_created_idx',
            'messages_deleted_created_idx',
        ] as $index) {
            $this->dropIndex('messages', $index);
        }

        $this->dropIndex('notifications', 'notifications_user_read_created_idx');

        foreach ([
            'attendance_user_clock_in_idx',
            'attendance_clock_in_idx',
            'attendance_user_status_idx',
        ] as $index) {
            $this->dropIndex('attendance', $index);
        }

        foreach ([
            'tasks_assigned_created_idx',
            'tasks_assigner_created_idx',
            'tasks_department_created_idx',
            'tasks_review_status_created_idx',
            'tasks_reviewed_by_at_idx',
            'tasks_due_status_idx',
        ] as $index) {
            $this->dropIndex('tasks', $index);
        }
    }

    private function dropIndex(string $tableName, string $indexName): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        } catch (Throwable) {
            // The index may not exist on older or partially migrated installations.
        }
    }
};
