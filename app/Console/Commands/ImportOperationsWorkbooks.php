<?php

namespace App\Console\Commands;

use App\Models\PerformanceRegister;
use App\Models\User;
use App\Services\OperationsReporting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class ImportOperationsWorkbooks extends Command
{
    protected $signature = 'operations:import-workbooks {snapshot} {--apply : Publish the reconciled records and source archive}';

    protected $description = 'Import a validated Operations workbook snapshot without replacing existing reports';

    public function handle(): int
    {
        $source = json_decode(File::get($this->argument('snapshot')), true, flags: JSON_THROW_ON_ERROR);
        $staff = User::internalStaff()->get();
        $custodian = $staff->first(fn (User $user) => $user->isSuperAdmin());
        if (! $custodian) {
            throw new RuntimeException('No system custodian for source rows without an identified owner.');
        }

        $aliases = [
            'samuel perprah' => 'Samuel Pepprah Appiah',
            'uches' => 'Uche Emmanuel',
            'william jr (nsp)' => 'William Hamilton Junior',
            'wiliam jr' => 'William Hamilton Junior',
            'john (nsp)' => 'John Fiifi Arhin',
        ];
        $normalize = fn (string $name) => Str::lower(Str::squish($name));
        $resolve = function (?string $value) use ($staff, $aliases, $normalize): ?int {
            if (! filled($value)) {
                return null;
            }
            $key = $normalize($value);
            $alias = isset($aliases[$key]) ? $normalize($aliases[$key]) : $key;
            $exact = $staff->filter(fn (User $user) => $normalize($user->name) === $alias);
            if ($exact->count() === 1) {
                return $exact->first()->id;
            }
            $matches = $staff->filter(fn (User $user) => str_starts_with($normalize($user->name), $key.' ')
                || str_contains(' '.$normalize($user->name).' ', ' '.$key.' '));
            return $matches->count() === 1 ? $matches->first()->id : null;
        };
        $rows = collect($source['records']);
        if ($rows->isEmpty() || $rows->pluck('reference')->unique()->count() !== $rows->count()
            || $rows->contains(fn ($row) => ! preg_match('/^OPS-XLSX-[a-f0-9]{24}$/', $row['reference'])
                || ! in_array($row['kind'], ['activation', 'posm_deployment', 'team_allocation', 'fabrication'], true)
                || ! array_key_exists($row['status'], OperationsReporting::STATUSES))) {
            throw new RuntimeException('Invalid or duplicate Operations workbook records.');
        }
        $archivePath = storage_path('app/private/operations-workbook-snapshot.json');
        $previousArchive = is_file($archivePath) ? File::get($archivePath) : null;
        $archive = json_encode($source['archive'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $apply = $this->option('apply');
        $report = ['workbooks' => count($source['inventory']), 'tabs' => count($source['archive']),
            'source_records' => $rows->count(), 'undated' => 0, 'needs_owner' => 0, 'inserted' => [], 'existing' => []];

        try {
            DB::transaction(function () use ($rows, $custodian, $resolve, $apply, $archivePath, $archive, &$report) {
                foreach ($rows as $row) {
                    $kind = $row['kind'];
                    $data = $row['data'];
                    $owner = $resolve($row['lead']);
                    $data['import_no_owner'] = $owner === null;
                    $data['collaborator_ids'] = collect($row['supporting'])->filter()
                        ->flatMap(fn ($value) => preg_split('/\s*[\/&]\s*/', $value))
                        ->map($resolve)->filter(fn ($id) => $id !== null && $id !== $owner)->unique()->values()->all();
                    $report['undated'] += (int) ($data['import_undated'] ?? false);
                    $report['needs_owner'] += (int) ($owner === null);

                    $exists = PerformanceRegister::where('department', OperationsReporting::DEPARTMENT)
                        ->where('kind', $kind)->where('reference', $row['reference'])->exists();
                    $bucket = $exists ? 'existing' : 'inserted';
                    $report[$bucket][$kind] = ($report[$bucket][$kind] ?? 0) + 1;
                    if ($exists || ! $apply) {
                        continue;
                    }
                    PerformanceRegister::create(array_diff_key($row, ['supporting' => true, 'lead' => true, 'data' => true]) + [
                        'department' => OperationsReporting::DEPARTMENT,
                        'owner_id' => $owner ?? $custodian->id,
                        // Source publication is not a staff sign-off or a completion decision.
                        'approved_at' => now(),
                        'data' => $data,
                    ]);
                }

                if ($apply) {
                    File::ensureDirectoryExists(dirname($archivePath), 0700);
                    File::replace($archivePath, $archive, 0600);
                }
            });
        } catch (\Throwable $e) {
            if ($apply) {
                if ($previousArchive === null) {
                    File::delete($archivePath);
                } else {
                    File::replace($archivePath, $previousArchive, 0600);
                }
            }
            throw $e;
        }

        $this->line(json_encode(['mode' => $apply ? 'published' : 'preview'] + $report, JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
