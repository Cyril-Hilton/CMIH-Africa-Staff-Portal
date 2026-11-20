<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\ExecutiveCommandCentre;
use App\Services\ExecutiveExport;
use App\Services\ExecutiveExportFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ExecutiveExportController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->canViewExecutiveActivity(), 403);
        $command = app(ExecutiveCommandCentre::class)->build($request)['command'];
        $context = Arr::only($command['f'], ['exec_view', 'exec_period', 'exec_date', 'exec_department', 'exec_person', 'exec_project', 'exec_activation', 'exec_brief', 'exec_q', 'exec_scope', 'exec_attention']);
        $context += ['exec_view' => $command['view'], 'exec_period' => $command['period'], 'exec_date' => $command['anchor']->toDateString()];
        if ($request->filled('activity_section')) {
            $context['activity_section'] = $request->query('activity_section');
        }

        return response()->view('portal.executive.export', ['command' => $command, 'context' => $context, 'report' => null])->header('Cache-Control', 'private, no-store');
    }

    public function preview(Request $request, ExecutiveExport $export)
    {
        $input = $export->validate($request);
        $report = $export->build($request, $input);

        return response()->view('portal.executive.export', ['command' => $report['command'], 'context' => $input['context'] ?? [], 'report' => $report])->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request, ExecutiveExport $export, ExecutiveExportFiles $files)
    {
        $input = $export->validate($request);
        $report = $export->build($request, $input);
        if (($input['selection_mode'] ?? 'all') === 'selected') {
            $keys = $input['records'] ?? [];
            if (! $keys || array_diff($keys, $report['records']->keys()->all())) {
                throw \Illuminate\Validation\ValidationException::withMessages(['records' => 'Choose at least one current record. If the source changed, refresh the preview.']);
            }
            $report['records'] = $report['records']->only($keys);
        }
        $format = $input['format'];
        $body = $format === 'xlsx' ? $files->xlsx($report) : $files->pdf($report);

        return response($body)->header('Content-Type', $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/pdf')->header('Content-Disposition', 'attachment; filename="cmih-executive-'.now()->format('Ymd-His').'.'.$format.'"')->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
