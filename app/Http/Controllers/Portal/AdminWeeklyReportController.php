<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AdminWeeklyReport;
use App\Models\AdminWeeklyReportLine;
use App\Models\User;
use App\Services\PerformanceWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminWeeklyReportController extends Controller
{
    private function authorizeReport(Request $r): void
    {
        abort_unless(User::normalizeDepartmentKey($r->user()->department) === 'hr_admin' || $r->user()->isCvoOrSuperAdmin(), 403);
    }

    public function index(Request $r)
    {
        $this->authorizeReport($r);
        $reports = AdminWeeklyReport::with(['lines.owner', 'preparer'])->orderByDesc('period_start')->paginate(6);
        $staff = User::humanAccounts()->where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $previous = AdminWeeklyReport::whereNotNull('approved_at')->with('lines')->orderByDesc('period_end')->first();

        return view('portal.admin-weekly.index', compact('reports', 'staff', 'previous'));
    }

    public function store(Request $r)
    {
        $this->authorizeReport($r);
        $r->validate(['period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start', 'lines' => 'required|array']);
        $data = [];
        foreach (config('admin_weekly') as $section => $spec) {
            foreach ($spec['rows'] as $code => $field) {
                $prefix = "lines.$section.$code";
                $v = $r->validate([$prefix.'.value_state' => 'required|in:reported,not_applicable,not_reported', $prefix.'.numeric_value' => 'nullable|numeric|min:0|max:999999999', $prefix.'.text_value' => 'nullable|string|max:5000', $prefix.'.status' => 'nullable|string|max:255', $prefix.'.issue' => 'nullable|string|max:5000', $prefix.'.action' => 'nullable|string|max:5000', $prefix.'.owner_id' => 'nullable|integer|exists:users,id', $prefix.'.due_date' => 'nullable|date']);
                $row = $v['lines'][$section][$code];
                if ($row['value_state'] === 'reported' && $field['type'] === 'number' && ! isset($row['numeric_value'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([$prefix.'.numeric_value' => 'Enter a value, or mark not reported / not applicable.']);
                }if ($row['value_state'] !== 'reported') {
                    $row['numeric_value'] = null;
                    $row['text_value'] = null;
                }if (in_array($code, ['attendance_rate', 'consumption_per_100km']) && isset($row['numeric_value']) && $code === 'attendance_rate' && $row['numeric_value'] > 100) {
                    throw \Illuminate\Validation\ValidationException::withMessages([$prefix.'.numeric_value' => 'Attendance cannot exceed 100%.']);
                }$data[] = collect($row)->only(['value_state', 'numeric_value', 'text_value', 'status', 'issue', 'action', 'owner_id', 'due_date'])->all() + ['section' => $section, 'code' => $code];
            }
        }
        $lookup = collect($data)->keyBy(fn ($row) => $row['section'].'.'.$row['code']);
        foreach ([['fleet.vehicles_available', 'fleet.total_vehicles'], ['assets.assigned_assets', 'assets.total_assets'], ['assets.damaged_assets', 'assets.total_assets'], ['assets.missing_assets', 'assets.total_assets']] as [$part,$total]) {
            if (isset($lookup[$part]['numeric_value'],$lookup[$total]['numeric_value']) && $lookup[$part]['numeric_value'] > $lookup[$total]['numeric_value']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['lines' => 'Component count cannot exceed its total: '.$part]);
            }
        }
        DB::transaction(function () use ($r, $data) {
            $exists = AdminWeeklyReport::where('prepared_by', $r->user()->id)->whereDate('period_start', $r->period_start)->whereDate('period_end', $r->period_end)->exists();
            if ($exists) {
                throw \Illuminate\Validation\ValidationException::withMessages(['period_start' => 'A report already exists for this preparer and period.']);
            }$report = AdminWeeklyReport::create(['period_start' => $r->period_start, 'period_end' => $r->period_end, 'prepared_by' => $r->user()->id]);
            foreach ($data as $row) {
                $report->lines()->create($row);
            }PerformanceWorkflow::record('admin_weekly', $report->id, $r->user()->id, 'submit', [], ['header' => $report->getAttributes(), 'lines' => $data], 'Admin weekly report submitted');
        });

        return back()->with('status', 'Admin weekly report submitted. HR reporting remains separate.');
    }

    public function correct(Request $r, AdminWeeklyReport $report, AdminWeeklyReportLine $line)
    {
        $this->authorizeReport($r);
        abort_unless((int) $report->prepared_by === $r->user()->id || $r->user()->hasFullHrAccess(), 403);
        abort_if((int) $line->report_id !== (int) $report->id, 404);
        $v = $r->validate(['revision' => 'required|integer', 'reason' => 'required|string|max:2000', 'value_state' => 'required|in:reported,not_applicable,not_reported', 'numeric_value' => 'nullable|numeric|min:0|max:999999999', 'text_value' => 'nullable|string|max:5000', 'status' => 'nullable|string|max:255', 'issue' => 'nullable|string|max:5000', 'action' => 'nullable|string|max:5000', 'owner_id' => 'nullable|integer|exists:users,id', 'due_date' => 'nullable|date']);
        $spec = config('admin_weekly.'.$line->section.'.rows.'.$line->code);
        abort_unless($spec, 422);
        if ($v['value_state'] === 'reported' && $spec['type'] === 'number' && ! isset($v['numeric_value'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['numeric_value' => 'Enter the reported figure.']);
        }
        if ($line->code === 'attendance_rate' && ($v['numeric_value'] ?? 0) > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages(['numeric_value' => 'Attendance cannot exceed 100%.']);
        }
        if ($v['value_state'] !== 'reported') {
            $v['numeric_value'] = null;
            $v['text_value'] = null;
        }
        DB::transaction(function () use ($r, $report, $line, $v) {
            $i = AdminWeeklyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_if((int) $i->revision !== (int) $v['revision'], 409);
            abort_unless($i->status === 'returned', 422, 'Return the report for correction first.');
            $entry = $i->lines()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            $before = $entry->getAttributes();
            $entry->update(collect($v)->except(['reason', 'revision'])->all());
            $i->increment('revision');
            PerformanceWorkflow::record('admin_weekly', $i->id, $r->user()->id, 'correct_line', $before, $entry->getAttributes(), $v['reason']);
        });

        return back()->with('status', 'Admin reporting row corrected. Resubmit when ready.');
    }

    public function transition(Request $r, AdminWeeklyReport $report)
    {
        $this->authorizeReport($r);
        $v = $r->validate(['action' => 'required|in:return,resubmit', 'reason' => 'required|string|max:2000', 'revision' => 'required|integer']);
        DB::transaction(function () use ($r, $report, $v) {
            $i = AdminWeeklyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_if((int) $i->revision !== (int) $v['revision'], 409);
            $before = $i->getAttributes();
            if ($v['action'] === 'return') {
                abort_unless(($r->user()->isLineManager() || $r->user()->isCvoOrSuperAdmin()) && (int) $i->prepared_by !== $r->user()->id, 403);
                abort_unless($i->status === 'submitted', 422);
                $i->status = 'returned';
            } else {
                abort_unless((int) $i->prepared_by === $r->user()->id, 403);
                abort_unless($i->status === 'returned', 422);
                $i->status = 'submitted';
            }
            $i->revision++;
            $i->save();
            PerformanceWorkflow::record('admin_weekly', $i->id, $r->user()->id, $v['action'], $before, $i->getAttributes(), $v['reason']);
        });

        return back()->with('status', 'Admin report workflow updated.');
    }

    public function approve(Request $r, AdminWeeklyReport $report)
    {
        $this->authorizeReport($r);
        abort_unless($r->user()->isLineManager() || $r->user()->isCvoOrSuperAdmin(), 403);
        abort_if((int) $report->prepared_by === $r->user()->id, 403);
        $v = $r->validate(['reason' => 'required|string|max:2000']);
        DB::transaction(function () use ($r, $report, $v) {
            $item = AdminWeeklyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_if($item->approved_at, 409);
            abort_unless($item->status === 'submitted', 422);
            $before = $item->getAttributes();
            $item->update(['status' => 'approved', 'approved_by' => $r->user()->id, 'approved_at' => now(), 'revision' => $item->revision + 1]);
            PerformanceWorkflow::record('admin_weekly', $item->id, $r->user()->id, 'approve', $before, $item->getAttributes(), $v['reason']);
        });

        return back()->with('status', 'Admin report approved.');
    }
}
