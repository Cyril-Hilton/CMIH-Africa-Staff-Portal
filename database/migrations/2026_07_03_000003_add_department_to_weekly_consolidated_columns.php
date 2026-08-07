<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_consolidated_columns', function (Blueprint $table) {
            $table->string('department')->nullable()->after('user_id');
        });

        $departmentMap = [
            'admin' => 'hr_admin',
            'transport' => 'hr_admin',
            'client_service' => 'client_relations',
            'operations' => 'operations_projects',
            'brands' => 'brands_marketing',
            'creative' => 'creatives',
            'hr_admin' => 'hr_admin',
            'finance' => 'finance',
            'client_relations' => 'client_relations',
            'operations_projects' => 'operations_projects',
            'brands_marketing' => 'brands_marketing',
            'creatives' => 'creatives',
        ];

        DB::table('weekly_consolidated_columns')
            ->join('users', 'weekly_consolidated_columns.user_id', '=', 'users.id')
            ->select('weekly_consolidated_columns.id', 'users.department')
            ->orderBy('weekly_consolidated_columns.id')
            ->get()
            ->each(function ($column) use ($departmentMap) {
                $department = strtolower(trim((string) $column->department));

                DB::table('weekly_consolidated_columns')
                    ->where('id', $column->id)
                    ->update([
                        'department' => $departmentMap[$department] ?? ($department ?: 'operations_projects'),
                    ]);
            });

        Schema::table('weekly_consolidated_columns', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'column_key']);
            $table->unique(['user_id', 'department', 'column_key']);
        });
    }

    public function down(): void
    {
        Schema::table('weekly_consolidated_columns', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'department', 'column_key']);
            $table->unique(['user_id', 'column_key']);
            $table->dropColumn('department');
        });
    }
};
