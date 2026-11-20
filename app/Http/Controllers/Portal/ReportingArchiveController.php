<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\ExecutiveExportFiles;
use App\Services\ReportingReset;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportingArchiveController extends Controller
{
    public function index(Request $request)
    {
        $this->access($request);
        $batches = DB::table('reporting_archive_batches')->latest('created_at')->get()->map(function ($batch) {
            $batch->manifest = json_decode($batch->manifest, true);

            return $batch;
        });

        return response()->view('portal.executive.archives', compact('batches'))->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request, string $batch, string $table, string $format)
    {
        $this->access($request);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $models = app(ReportingReset::class)->tables();
        abort_unless(isset($models[$table]) || $table === 'performance_outbox', 404);
        $entry = DB::table('reporting_archive_batches')->find($batch);
        abort_unless($entry, 404);
        $query = DB::table($table);
        if ($table === 'performance_report_packs') {
            $query->whereIn('id', DB::table('reporting_archive_records')->select('source_id')->where('source_table', $table)->where('batch_id', $batch));
        } else {
            $query->where('reporting_archive_batch_id', $batch);
        }
        $records = $query->orderBy('id')->get()->map(function ($row) use ($table) {
            $fields = [];
            foreach ((array) $row as $key => $value) {
                if ($key === 'reporting_archive_batch_id') {
                    continue;
                }
                $decoded = is_string($value) ? json_decode($value, true) : null;
                foreach (Arr::dot([$key => is_array($decoded) ? $decoded : $value]) as $field => $scalar) {
                    if (preg_match('/password|token|secret|bank|salary/i', $field)) {
                        continue;
                    }
                    $fields[Str::headline(str_replace('.', ' / ', $field))] = is_scalar($scalar) ? $scalar : ($scalar === null ? 'Setup needed' : json_encode($scalar, JSON_UNESCAPED_UNICODE));
                }
            }

            return ['key' => $table.':'.$row->id, 'section' => 'reports', 'title' => ($row->title ?? $row->project_name ?? $row->campaign_name ?? Str::headline($table)).' #'.$row->id, 'owner' => null, 'fields' => $fields, 'url' => null];
        })->keyBy('key');
        $report = ['records' => $records, 'period' => 'Archived reporting · '.$entry->created_at, 'generated' => now()->format('d M Y, H:i').' Africa/Accra', 'requestedBy' => $request->user()->name, 'input' => ['scope' => 'Archived batch '.$batch, 'table' => $table]];
        $files = app(ExecutiveExportFiles::class);
        $body = $format === 'xlsx' ? $files->xlsx($report) : $files->pdf($report);

        return response($body)->header('Content-Type', $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/pdf')->header('Content-Disposition', 'attachment; filename="archive-'.$table.'-'.$batch.'.'.$format.'"')->header('Cache-Control', 'private, no-store');
    }

    private function access(Request $request): void
    {
        abort_unless($request->user()?->isActive() && ($request->user()->isCvoOrSuperAdmin() || $request->user()->canAuditHrAndFinance()), 403);
    }
}
