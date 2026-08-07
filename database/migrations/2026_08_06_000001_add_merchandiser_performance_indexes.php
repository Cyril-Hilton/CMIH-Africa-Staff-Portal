<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->index('merchandiser_attendances', ['user_id', 'clock_in_time'], 'merch_att_user_clock_idx');
        $this->index('merchandiser_attendances', ['outlet_id', 'clock_in_time'], 'merch_att_outlet_clock_idx');
        $this->index('merchandiser_attendances', ['status', 'clock_in_time'], 'merch_att_status_clock_idx');

        $this->index('merchandiser_pcm_clockins', ['user_id', 'clocked_in_at'], 'merch_pcm_user_clock_idx');
        $this->index('merchandiser_pcm_clockins', ['kd_id', 'clocked_in_at'], 'merch_pcm_kd_clock_idx');
        $this->index('merchandiser_pcm_clockins', ['status', 'clocked_in_at'], 'merch_pcm_status_clock_idx');

        $this->index('merchandiser_pjp_clockins', ['user_id', 'clocked_in_at'], 'merch_pjp_user_clock_idx');
        $this->index('merchandiser_pjp_clockins', ['pjp_id', 'clocked_in_at'], 'merch_pjp_pjp_clock_idx');
        $this->index('merchandiser_pjp_clockins', ['status', 'clocked_in_at'], 'merch_pjp_status_clock_idx');

        $this->index('merchandiser_locations', ['user_id', 'recorded_at'], 'merch_loc_user_recorded_idx');

        $this->index('merchandiser_outlet_assignments', ['status', 'assigned_date'], 'merch_assign_status_date_idx');
        $this->index('merchandiser_outlet_assignments', ['source', 'assigned_date'], 'merch_assign_source_date_idx');

        $this->index('merchandiser_visits', ['user_id', 'created_at'], 'merch_visits_user_created_idx');
        $this->index('merchandiser_visits', ['outlet_id', 'created_at'], 'merch_visits_outlet_created_idx');
        $this->index('merchandiser_visits', ['route_assignment_id', 'created_at'], 'merch_visits_route_created_idx');

        $this->index('outlets', ['kd_id', 'created_at'], 'outlets_kd_created_idx');
        $this->index('outlets', ['registered_by', 'created_at'], 'outlets_registered_created_idx');
    }

    public function down(): void
    {
        foreach ([
            'merchandiser_attendances' => ['merch_att_user_clock_idx', 'merch_att_outlet_clock_idx', 'merch_att_status_clock_idx'],
            'merchandiser_pcm_clockins' => ['merch_pcm_user_clock_idx', 'merch_pcm_kd_clock_idx', 'merch_pcm_status_clock_idx'],
            'merchandiser_pjp_clockins' => ['merch_pjp_user_clock_idx', 'merch_pjp_pjp_clock_idx', 'merch_pjp_status_clock_idx'],
            'merchandiser_locations' => ['merch_loc_user_recorded_idx'],
            'merchandiser_outlet_assignments' => ['merch_assign_status_date_idx', 'merch_assign_source_date_idx'],
            'merchandiser_visits' => ['merch_visits_user_created_idx', 'merch_visits_outlet_created_idx', 'merch_visits_route_created_idx'],
            'outlets' => ['outlets_kd_created_idx', 'outlets_registered_created_idx'],
        ] as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($indexes): void {
                foreach ($indexes as $index) {
                    try {
                        $blueprint->dropIndex($index);
                    } catch (Throwable) {
                        // Older production copies may not have every optional index.
                    }
                }
            });
        }
    }

    private function index(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        $existing = collect(Schema::getIndexes($table))
            ->pluck('name')
            ->map(fn ($index) => strtolower((string) $index))
            ->all();

        if (in_array(strtolower($name), $existing, true)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
            $blueprint->index($columns, $name);
        });
    }
};
