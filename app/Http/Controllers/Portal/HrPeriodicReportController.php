<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\HrPeriodicReport;
use App\Models\HrReportEntry;
use App\Models\User;
use App\Services\PerformanceMath;
use App\Services\PerformanceWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HrPeriodicReportController extends Controller
{
    private function authorizeReport(Request $r): void
    {
        abort_unless($r->user()->hasFullHrAccess() || (User::normalizeDepartmentKey($r->user()->department) === 'hr_admin' && in_array($r->user()->hrLevel(), [1, 2], true)), 403);
    }

    private function schema(Request $r): array
    {
        return collect(config('hr_reporting'))->filter(fn ($s) => empty($s['sensitive']) || $r->user()->hasFullHrAccess())->all();
    }

    public function index(Request $r)
    {
        $this->authorizeReport($r);
        $r->validate(['frequency' => 'nullable|in:weekly,monthly']);
        $reports = HrPeriodicReport::with(['entries' => fn ($q) => $q->whereIn('section', array_keys($this->schema($r))), 'previous.entries' => fn ($q) => $q->whereIn('section', array_keys($this->schema($r))), 'preparer'])->when($r->frequency, fn ($q) => $q->where('frequency', $r->frequency))->orderByDesc('period_start')->paginate(6);
        $schema = $this->schema($r);
        $staff = User::humanAccounts()->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all();

        return view('portal.hr-reporting.index', compact('reports', 'schema', 'staff'));
    }

    public function create(Request $r)
    {
        $this->authorizeReport($r);
        $v = $r->validate(['frequency' => 'required|in:weekly,monthly', 'period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start', 'prepared_on' => 'required|date']);
        DB::transaction(function () use ($v, $r) {
            $old = HrPeriodicReport::where('frequency', $v['frequency'])->whereDate('period_start', $v['period_start'])->whereDate('period_end', $v['period_end'])->where('prepared_by', $r->user()->id)->exists();
            if ($old) {
                throw \Illuminate\Validation\ValidationException::withMessages(['period_start' => 'This report period already exists.']);
            }$prior = HrPeriodicReport::where('frequency', $v['frequency'])->whereNotNull('approved_at')->whereDate('period_end', '<', $v['period_start'])->orderByDesc('period_end')->first();
            $i = HrPeriodicReport::create($v + ['prepared_by' => $r->user()->id, 'compared_report_id' => $prior?->id, 'section_states' => array_fill_keys(array_keys(config('hr_reporting')), 'not_reported')]);
            PerformanceWorkflow::record('hr_report', $i->id, $r->user()->id, 'create', [], $i->getAttributes(), 'HR report created from department format');
        });

        return back()->with('status', 'HR report draft created. Add the required section rows.');
    }

    public function populateSources(Request $r, HrPeriodicReport $report)
    {
        $this->authorizeReport($r);
        abort_unless($r->user()->hasFullHrAccess(), 403);
        $v = $r->validate(['revision' => 'required|integer']);
        $created = DB::transaction(function () use ($r, $report, $v) {
            $locked = HrPeriodicReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['draft', 'returned']), 409, 'Only draft or returned reports can be populated.');
            abort_unless((int) $locked->revision === (int) $v['revision'], 409, 'Report changed. Refresh before populating.');
            abort_unless($locked->period_start->lte(today()) && $locked->period_end->gte(today()), 422, 'Automatic roster snapshots are available for the current reporting period only. Historical rosters must be confirmed separately.');
            abort_if($locked->period_start->diffInDays($locked->period_end) > 366, 422);
            $staff = User::internalStaff()->where('status', 'active')->get();
            $expected = 0;
            $attended = 0;
            $punctual = 0;
            foreach ($staff as $person) {
                $summary = \App\Services\PerformanceScoringService::attendanceSummary($person, $locked->period_start, $locked->period_end);
                $expected += $summary['expected_workdays'];
                $attended += $summary['attendance_days'];
                $punctual += $summary['punctual_days'];
            }
            $new = $staff->filter(fn ($person) => $person->start_date && $person->start_date->betweenIncluded($locked->period_start, today()))->count();
            $note = 'Portal source snapshot as of '.today()->toDateString().'. Active internal staff roster; starters require recorded employment start dates. Expected attendance excludes approved leave, weekends and configured non-working days. Future days excluded.';
            $rows = [
                ['metrics', ['metric' => 'Headcount', 'actual' => $staff->count(), 'notes' => $note]],
                ['metrics', ['metric' => 'New starters', 'actual' => $new, 'notes' => $note]],
                ['metrics', ['metric' => 'Absence rate', 'actual' => PerformanceMath::ratio((float) max(0, $expected - $attended), (float) $expected), 'numerator' => max(0, $expected - $attended), 'denominator' => $expected, 'notes' => $note]],
                ['attendance_leave', ['metric' => 'Lateness', 'number' => max(0, $attended - $punctual), 'notes' => $note]],
            ];
            $created = 0;
            $before = $locked->getAttributes();
            $states = $locked->section_states;
            foreach ($rows as [$section,$data]) {
                $exists = $locked->entries()->where('section', $section)->get()->contains(fn ($entry) => ($entry->data['metric'] ?? null) === $data['metric']);
                if ($exists) {
                    continue;
                }
                $entry = HrReportEntry::create(['report_id' => $locked->id, 'section' => $section, 'data' => $data + ['_source_staff_ids' => $staff->pluck('id')->all(), '_source_as_of' => today()->toDateString()]]);
                PerformanceWorkflow::record('hr_report_entry', $entry->id, $r->user()->id, 'populate_source', [], $entry->getAttributes(), 'Current portal roster and attendance snapshot');
                $states[$section] = 'reported';
                $created++;
            }
            if ($created) {
                $locked->section_states = $states;
                $locked->revision++;
                $locked->save();
                PerformanceWorkflow::record('hr_report', $locked->id, $r->user()->id, 'populate_sources', $before, $locked->getAttributes(), 'Populated '.$created.' missing source-backed rows.');
            }

            return $created;
        });

        return back()->with('status', $created.' source-backed rows added. Existing rows were retained. Review all figures before submitting.');
    }

    public function entry(Request $r, HrPeriodicReport $report, ?HrReportEntry $entry = null)
    {
        $this->authorizeReport($r);
        abort_if($entry && (int) $entry->report_id !== (int) $report->id, 404);
        if ($entry) {
            $r->validate(['reason' => 'required|string|max:2000']);
        }
        abort_unless((int) $report->prepared_by === $r->user()->id || $r->user()->hasFullHrAccess(), 403);
        $v = $r->validate(['section' => ['required', Rule::in(array_keys($this->schema($r)))], 'revision' => 'required|integer', 'data' => 'required|array']);
        abort_if($entry && $entry->section !== $v['section'], 422);
        $schema = $this->schema($r)[$v['section']];
        $rules = [];
        foreach ($schema['fields'] as $field => $definition) {
            $rules['data.'.$field] = match ($definition['type']) {
                'number' => 'nullable|numeric|min:0|max:999999999','date' => 'nullable|date','owner' => 'nullable|integer|exists:users,id','select' => ['nullable', Rule::in($definition['options'])],default => 'nullable|string|max:5000'
            };
        }$fields = $r->validate($rules)['data'] ?? [];
        if (isset($schema['fields']['metric']) && empty($fields['metric'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['data.metric' => 'Choose a metric or activity.']);
        }
        if ($v['section'] === 'training' && empty($fields['training_title'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['data.training_title' => 'Enter the training title.']);
        }
        if (isset($fields['starts_on'],$fields['ends_on']) && $fields['ends_on'] < $fields['starts_on']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['data.ends_on' => 'End date cannot precede the start date.']);
        }
        if ($v['section'] === 'metrics' && in_array($fields['metric'], ['Absence rate', 'Training completion rate'])) {
            $fields['actual'] = PerformanceMath::ratio(isset($fields['numerator']) ? (float) $fields['numerator'] : null, isset($fields['denominator']) ? (float) $fields['denominator'] : null);
            if (isset($fields['numerator'],$fields['denominator']) && $fields['numerator'] > $fields['denominator']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['data.numerator' => 'The numerator cannot exceed the denominator.']);
            }$fields['calculation'] = 'numerator / denominator × 100; missing or zero denominator is unmeasured';
        }
        if ($v['section'] === 'training') {
            $fields['compliance_percent'] = PerformanceMath::ratio(isset($fields['completed']) ? (float) $fields['completed'] : null, isset($fields['required_completions']) ? (float) $fields['required_completions'] : null);
            if (isset($fields['completed'],$fields['required_completions']) && $fields['completed'] > $fields['required_completions']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['data.completed' => 'Completions cannot exceed required completions.']);
            }
        }
        DB::transaction(function () use ($v, $fields, $report, $r, $entry) {
            $i = HrPeriodicReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_if((int) $i->revision !== (int) $v['revision'], 409);
            abort_unless(in_array($i->status, ['draft', 'returned']), 422, 'Return this report before changing submitted or approved figures.');
            if ($v['section'] === 'metrics' && $i->entries()->where('section', 'metrics')->when($entry, fn ($q) => $q->where('id', '!=', $entry->id))->get()->contains(fn ($e) => ($e->data['metric'] ?? null) === $fields['metric'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['data.metric' => 'This key metric is already recorded; correct the original row instead of duplicating totals.']);
            }$before = $entry?->data ?? [];
            if ($entry) {
                $e = $i->entries()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
                $before = $e->data;
                $e->update(['data' => $fields]);
            } else {
                $e = $i->entries()->create(['section' => $v['section'], 'data' => $fields]);
            }
            $states = $i->section_states;
            $states[$v['section']] = 'reported';
            $i->section_states = $states;
            $i->revision++;
            $i->save();
            PerformanceWorkflow::record('hr_report', $i->id, $r->user()->id, $entry ? 'correct_entry' : 'add_entry', $before, ['entry_id' => $e->id, 'section' => $v['section'], 'data' => $fields], $entry ? $r->input('reason') : 'Department HR reporting row added');
        });

        return back()->with('status', 'HR reporting row recorded.');
    }

    public function section(Request $r, HrPeriodicReport $report)
    {
        $this->authorizeReport($r);
        abort_unless((int) $report->prepared_by === $r->user()->id || $r->user()->hasFullHrAccess(), 403);
        $v = $r->validate(['section' => ['required', Rule::in(array_keys($this->schema($r)))], 'state' => 'required|in:not_applicable,not_reported', 'reason' => 'required|string|max:2000', 'revision' => 'required|integer']);
        DB::transaction(function () use ($r, $report, $v) {
            $i = HrPeriodicReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_if((int) $i->revision !== (int) $v['revision'], 409);
            abort_unless(in_array($i->status, ['draft', 'returned']), 422);
            abort_if($i->entries()->where('section', $v['section'])->exists(), 422, 'A section containing records cannot be marked empty or not applicable.');
            $before = $i->section_states;
            $states = $before;
            $states[$v['section']] = $v['state'];
            $i->section_states = $states;
            $i->revision++;
            $i->save();
            PerformanceWorkflow::record('hr_report', $i->id, $r->user()->id, 'section_state', $before, $states, $v['reason']);
        });

        return back()->with('status', 'Section reporting status updated.');
    }

    public function review(Request $r, HrPeriodicReport $report)
    {
        $this->authorizeReport($r);
        $v = $r->validate(['action' => 'required|in:submit,approve,return', 'reason' => 'required|string|max:2000', 'revision' => 'required|integer']);
        DB::transaction(function () use ($v, $r, $report) {
            $i = HrPeriodicReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_if((int) $i->revision !== (int) $v['revision'], 409);
            $before = $i->getAttributes();
            if ($v['action'] === 'submit') {
                abort_unless((int) $i->prepared_by === $r->user()->id && in_array($i->status, ['draft', 'returned']), 403);
                $i->status = 'submitted';
            } else {
                abort_unless($r->user()->hasFullHrAccess() && (int) $i->prepared_by !== $r->user()->id, 403);
                abort_unless($i->status === 'submitted', 422);
                $i->status = $v['action'] === 'approve' ? 'approved' : 'returned';
                if ($v['action'] === 'approve') {
                    $i->approved_at = now();
                    $i->approved_by = $r->user()->id;
                }
            }$i->review_notes = $v['reason'];
            $i->revision++;
            $i->save();
            PerformanceWorkflow::record('hr_report', $i->id, $r->user()->id, $v['action'], $before, $i->getAttributes(), $v['reason']);
        });

        return back()->with('status', 'HR report workflow updated.');
    }
}
