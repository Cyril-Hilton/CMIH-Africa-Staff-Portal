<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReportingReset
{
    public function tables(): array
    {
        return collect(config('reporting_archive.models'))->mapWithKeys(fn ($class) => [(new $class)->getTable() => $class])->filter(fn ($class, $table) => Schema::hasTable($table))->all();
    }

    public function protectedState(): array
    {
        $result = [];
        $excluded = array_merge(array_diff(array_keys($this->tables()), ['performance_report_packs']), ['performance_outbox', 'reporting_archive_batches', 'reporting_archive_records', 'crm_account_directory']);
        $tables = collect(Schema::getTables())->pluck('name')->reject(fn ($name) => in_array($name, $excluded, true) || str_starts_with($name, 'sqlite_'));
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $hash = hash_init('sha256');
            $count = 0;
            foreach (DB::table($table)->orderBy(Schema::getColumnListing($table)[0])->cursor() as $row) {
                hash_update($hash, json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $count++;
            }
            $result[$table] = ['rows' => $count, 'sha256' => hash_final($hash)];
        }

        return $result;
    }

    public function plan(): array
    {
        $tables = [];
        foreach ($this->tables() as $table => $class) {
            $tables[$table] = $class::count();
        }

        return ['tables' => $tables, 'protected' => $this->protectedState(), 'accounts' => $this->accounts()->count()];
    }

    public function accounts(bool $includeDirectory = true, ?string $archiveBatch = null): \Illuminate\Support\Collection
    {
        $names = collect();
        if ($includeDirectory && Schema::hasTable('crm_account_directory')) {
            $names = $names->merge(DB::table('crm_account_directory')->pluck('client_name'));
        }
        foreach (config('reporting_archive.account_models') as $class) {
            $query = $class::query();
            if ($archiveBatch) {
                $query->withoutGlobalScope('active_reporting')->where('reporting_archive_batch_id', $archiveBatch);
            }
            if ($class === \App\Models\CrmPipelineProject::class) {
                $query->where('source_period_confirmed', true);
            }
            $names = $names->merge($query->pluck('client_name'));
        }

        return $names->filter(fn ($name) => trim((string) $name) !== '')->unique(ClientRelationsCentre::key(...))->values();
    }

    public function archive(User $actor, string $label, ?string $backupPath = null): array
    {
        abort_unless($actor->isCvoOrSuperAdmin(), 403);

        return DB::transaction(function () use ($actor, $label, $backupPath) {
            $before = $this->protectedState();
            $accounts = $this->accounts();
            $batch = (string) Str::uuid();
            $counts = [];
            foreach ($accounts as $name) {
                DB::table('crm_account_directory')->insertOrIgnore(['client_name' => $name, 'name_key' => ClientRelationsCentre::key($name), 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($this->tables() as $table => $class) {
                if ($table === 'performance_report_packs') {
                    $ids = $class::pluck('id');
                    foreach ($ids as $id) {
                        DB::table('reporting_archive_records')->insert(['batch_id' => $batch, 'source_table' => $table, 'source_id' => $id]);
                    }
                    $counts[$table] = $ids->count();

                    continue;
                }
                $counts[$table] = DB::table($table)->whereNull('reporting_archive_batch_id')->update(['reporting_archive_batch_id' => $batch]);
            }
            // Preserve operational notifications (warehouse, leave, payroll etc.) while stopping obsolete report alerts.
            $pending = DB::table('performance_outbox')->whereNull('reporting_archive_batch_id')->where(function ($q) {
                foreach (['/portal/tasks/', '/portal/creative?request=', '/portal/operations/handovers', 'weekly_department=', 'exec_view=', '/portal/performance', '/portal/client-relations', '/portal/finance?finance_tab=', '/portal/operations?ops_review='] as $part) {
                    $q->orWhere('url', 'like', '%'.$part.'%');
                }
            });
            $counts['performance_outbox'] = $pending->update(['reporting_archive_batch_id' => $batch]);
            $after = $this->protectedState();
            if ($before !== $after) {
                throw new \RuntimeException('Protected operational records changed; the archive was rolled back.');
            }
            $manifest = ['tables' => $counts, 'accounts_preserved' => DB::table('crm_account_directory')->count(), 'protected' => $after, 'source' => 'User-authorized departmental reporting reset', 'reversible' => true];
            DB::table('reporting_archive_batches')->insert(['id' => $batch, 'label' => $label, 'actor_id' => $actor->id, 'manifest' => json_encode($manifest, JSON_UNESCAPED_UNICODE), 'backup_path' => $backupPath, 'created_at' => now()]);

            return ['id' => $batch] + $manifest;
        });
    }

    public function restore(User $actor, string $batch): void
    {
        abort_unless($actor->isCvoOrSuperAdmin(), 403);
        DB::transaction(function () use ($batch) {
            $row = DB::table('reporting_archive_batches')->find($batch);
            abort_unless($row, 404);
            DB::table('reporting_archive_records')->where('batch_id', $batch)->delete();
            foreach (array_merge(array_diff(array_keys($this->tables()), ['performance_report_packs']), ['performance_outbox']) as $table) {
                DB::table($table)->where('reporting_archive_batch_id', $batch)->update(['reporting_archive_batch_id' => null]);
            }
            $manifest = json_decode($row->manifest, true);
            $manifest['restored_at'] = now()->toIso8601String();
            DB::table('reporting_archive_batches')->where('id', $batch)->update(['manifest' => json_encode($manifest, JSON_UNESCAPED_UNICODE)]);
        });
    }
}
