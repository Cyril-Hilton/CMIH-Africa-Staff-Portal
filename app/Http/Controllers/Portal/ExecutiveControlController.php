<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CrmPipelineProject;
use App\Models\ProjectExecutionControl;
use App\Models\User;
use App\Services\PerformanceWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExecutiveControlController extends Controller
{
    public function team(Request $r)
    {
        abort_unless($r->user()->canViewExecutiveActivity(), 403);
        $v = $r->validate(['user_id' => 'required|integer|exists:users,id', 'base_country' => 'required|string|max:100', 'reason' => 'required|string|max:2000']);
        abort_unless(User::internalStaff()->whereKey($v['user_id'])->exists(), 422);
        DB::transaction(function () use ($r, $v) {
            $key = 'team-country:'.$v['user_id'];
            $before = DB::table('executive_tracker_settings')->where('key', $key)->first();
            DB::table('executive_tracker_settings')->updateOrInsert(['key' => $key], ['data' => json_encode(['base_country' => $v['base_country'], 'user_id' => $v['user_id']]), 'updated_by' => $r->user()->id, 'updated_at' => now(), 'created_at' => $before?->created_at ?? now()]);
            $after = DB::table('executive_tracker_settings')->where('key', $key)->first();
            PerformanceWorkflow::record('executive_team_setting', $after->id, $r->user()->id, 'base_country_recorded', $before ? (array) $before : [], (array) $after, $v['reason']);
        });

        return back()->with('status', 'Staff base country recorded with change history.');
    }

    public function reminders(Request $r)
    {
        abort_unless($r->user()->canViewExecutiveActivity(), 403);
        $v = $r->validate(['enabled' => 'required|boolean', 'mode' => 'required|in:preview,live', 'first_days' => 'required|integer|min:1|max:60', 'second_days' => 'required|integer|min:1|lte:first_days', 'daily_days' => 'required|integer|min:1|lte:second_days', 'escalation_user_id' => 'nullable|integer|exists:users,id', 'overdue_enabled' => 'required|boolean', 'escalation_enabled' => 'required|boolean', 'subject_prefix' => 'required|string|max:120', 'signature' => 'nullable|string|max:200', 'rules' => 'nullable|array', 'rules.*' => 'in:'.implode(',', config('executive_tracker.reminders.rules')), 'reason' => 'required|string|max:2000']);
        if (! empty($v['escalation_user_id']) && ! User::internalStaff()->where('status', 'active')->whereKey($v['escalation_user_id'])->exists()) {
            throw ValidationException::withMessages(['escalation_user_id' => 'Choose an active internal manager.']);
        }$data = collect($v)->except('reason')->all();
        $data['rules'] = $v['rules'] ?? [];
        $row = DB::table('executive_tracker_settings')->where('key', 'default')->first();
        DB::table('executive_tracker_settings')->updateOrInsert(['key' => 'default'], ['data' => json_encode($data), 'updated_by' => $r->user()->id, 'updated_at' => now(), 'created_at' => $row?->created_at ?? now()]);
        $saved = DB::table('executive_tracker_settings')->where('key', 'default')->first();
        PerformanceWorkflow::record('executive_reminder_setting', $saved->id, $r->user()->id, 'update', $row ? (array) $row : [], (array) $saved, $v['reason']);

        return back()->with('status', 'Reminder settings saved. Portal delivery settings determine notification channels.');
    }

    public function show(Request $r, ProjectExecutionControl $control)
    {
        $project = $control->project;
        $activation = $control->activation;
        $owner = $project?->owner_id ?? $activation?->owner_id;
        abort_unless($r->user()->isActive() && ($r->user()->canViewExecutiveActivity() || (int) $owner === $r->user()->id || in_array($r->user()->id, $control->data['participant_ids'] ?? [], true) || ($activation && \App\Services\PerformanceAccess::manages($r->user(), 'operations_projects'))), 403);
        $safe = $control->data;
        if (! \App\Services\FinanceDashboard::canRead($r->user()) && ! \App\Services\OperationsReporting::manager($r->user())) {
            foreach (['resources', 'assets'] as $kind) {
                if (isset($safe[$kind]['rows'])) {
                    foreach ($safe[$kind]['rows'] as &$row) {
                        unset($row['unit_rate']);
                    }
                }
            }
        }unset($row);

        return view('portal.executive.shared-control', ['control' => $control, 'safe' => $safe, 'title' => $project?->project_name ?? $activation?->title, 'sourceUrl' => $project ? route('portal.project-journey', ['project' => $project->id]) : route('dashboard', ['weekly_department' => 'operations_projects', 'report_date' => $activation->period_start->toDateString()]), 'people' => User::internalStaff()->pluck('name', 'id'), 'history' => \App\Models\PerformanceEvent::with('actor:id,name')->where('entity_type', 'project_control')->where('entity_id', $control->id)->latest('id')->paginate(20)]);
    }

    public function activation(Request $r, \App\Models\PerformanceRegister $activation)
    {
        abort_unless($activation->kind === 'activation' && in_array($activation->department, User::departmentAliases('operations_projects')) && $activation->approved_at, 404);
        $project = new CrmPipelineProject(['project_name' => $activation->project ?: $activation->title, 'client_name' => $activation->client, 'owner_id' => $activation->owner_id, 'source_period_confirmed' => true]);

        return $this->save($r, $project, $activation);
    }

    public function save(Request $r, CrmPipelineProject $project, ?\App\Models\PerformanceRegister $activation = null)
    {
        abort_unless($r->user()->canViewExecutiveActivity(), 403);
        abort_unless($project->source_period_confirmed, 422, 'Confirm the project source period first.');
        $v = $r->validate(['section' => 'required|in:schedule,process,resources,assets,compliance,tasks', 'revision' => 'required|integer|min:0', 'reason' => 'required|string|max:2000', 'data' => 'required|array']);
        $section = $v['section'];
        $data = app(\App\Services\ExecutionPlan::class)->validate($section, $v['data'], $r->user());
        app(\App\Services\ExecutionPlan::class)->apply($r->user(), $project, $activation, [$section => $data], (int) $v['revision'], $v['reason']);

        return back()->with('status','Project controls updated. The previous values and recorder remain in the audit history.');
    }
}
