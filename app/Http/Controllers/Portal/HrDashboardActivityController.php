<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\HrDashboardActivity;
use App\Models\User;
use App\Services\Creative\CreativeUploadScreening;
use App\Services\HrDashboard;
use App\Services\{HrLiveRegisters,PerformanceWorkflow,NotificationService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class HrDashboardActivityController extends Controller
{
    public function save(Request $request, ?HrDashboardActivity $activity = null)
    {
        $user = $request->user();
        abort_unless(HrDashboard::canAccess($user), 403);
        $tabs = HrDashboard::tabs($user);
        if(HrLiveRegisters::full($user))abort_unless(HrLiveRegisters::canEdit($user),403);
        $tab = $activity?->tab ?? $request->input('tab');
        abort_unless(is_string($tab) && isset($tabs[$tab]), 403);
        if ($activity) {
            abort_unless($user->hasFullHrAccess() || in_array($user->id, [$activity->created_by, $activity->assigned_to]), 403);
        }

        $data = $request->validate([
            'category' => ['required', Rule::in(array_unique([...$tabs[$tab][1],...($activity?[$activity->category]:[])]))],
            'activity' => 'required|string|max:255',
            'staff_id' => 'nullable|integer',
            'department' => ['nullable', Rule::in(array_keys(config('performance.departments')))],
            'opened_on' => 'required|date_format:Y-m-d',
            'deadline' => 'required|date_format:Y-m-d|after_or_equal:opened_on',
            'assigned_to' => 'required|integer',
            'status' => ['required', Rule::in(HrDashboardActivity::STATUSES)],
            'priority'=>'nullable|in:High,Medium,Low',
            'linked_record'=>'nullable|string|regex:/^[a-z_]+:-?[0-9]+$/',
            'supporting_staff_ids'=>'nullable|array|max:100',
            'supporting_staff_ids.*'=>['integer','distinct',Rule::in(User::internalStaff()->where('status','active')->pluck('id')->all())],
            'remarks' => 'nullable|string|max:6000',
            'attachment' => 'nullable|file|mimes:pdf,docx,xlsx,pptx,txt,jpg,jpeg,png,webp|max:10240',
            'revision' => $activity ? 'required|integer|min:1' : 'nullable|integer',
        ]);
        if (!HrDashboard::team()->whereKey($data['assigned_to'])->where(fn ($q) => $q
            ->where('status', 'active')->orWhere('id', $activity?->assigned_to))->exists()) {
            throw ValidationException::withMessages(['assigned_to' => 'Choose a member of the HR/Admin team.']);
        }
        if (!empty($data['staff_id']) && !User::internalStaff()->whereKey($data['staff_id'])->exists()) {
            throw ValidationException::withMessages(['staff_id' => 'Choose an internal staff member.']);
        }

        $data['priority']=$data['priority']??'Medium';
        $data['supporting_staff_ids']=array_map('intval',$data['supporting_staff_ids']??[]);
        $link=$data['linked_record']??null;unset($data['linked_record']);
        $data['linked_type']=null;$data['linked_id']=null;
        if($link){
            [$kind,$id]=explode(':',$link);$schema=app(HrLiveRegisters::class)->schema($kind);
            $canonical=$tab==='operations'?'facilities':$tab;
            abort_unless($schema&&$schema['tab']===$canonical&&array_key_exists((int)$id,app(HrLiveRegisters::class)->options($kind,$user)),422,'Choose a linked record from this tab.');
            $data['linked_type']=$kind;$data['linked_id']=(int)$id;
        }
        $file = $request->file('attachment');
        $revision = $data['revision'] ?? null;
        unset($data['attachment'], $data['revision']);
        $newPath = null;
        if ($file) {
            app(CreativeUploadScreening::class)->check($file);
            $newPath = $file->store('hr-dashboard-activities', 'local');
            if (!$newPath) {
                throw new RuntimeException('The attachment could not be stored.');
            }
            $data['attachment'] = ['path' => $newPath, 'name' => basename($file->getClientOriginalName())];
        }
        $savedActivityId = null;
        try {
            $oldPath = DB::transaction(function () use ($data, $activity, $revision, $tab, $user, &$savedActivityId) {
                if (!$activity) {
                    $row=HrDashboardActivity::create($data + ['tab' => $tab, 'created_by' => $user->id,'completed_at'=>$data['status']==='Completed'?now():null]);
                    PerformanceWorkflow::record('hr_activity',$row->id,$user->id,'create',[],$row->getAttributes(),'HR activity created');
                    $savedActivityId=$row->id;
                    $this->notify($row);
                    return null;
                }
                $saved = HrDashboardActivity::query()->lockForUpdate()->findOrFail($activity->id);
                abort_if((int) $saved->revision !== (int) $revision, 409, 'This activity changed. Refresh it before editing.');
                if ($saved->status === 'Pending Approval' && in_array($data['status'], ['In Progress', 'Completed'], true)) {
                    throw ValidationException::withMessages(['status' => 'This activity is awaiting CVO approval. Use the approval action before proceeding or completing it.']);
                }
                $oldPath = $saved->attachment['path'] ?? null;
                $before=$saved->getAttributes();
                $completed=$data['status']==='Completed'?($saved->status==='Completed'?$saved->completed_at:now()):null;
                $saved->update($data + ['revision' => $saved->revision + 1,'completed_at'=>$completed,'approved_at'=>$data['status']==='Pending Approval'?null:$saved->approved_at,'approved_by'=>$data['status']==='Pending Approval'?null:$saved->approved_by]);
                PerformanceWorkflow::record('hr_activity',$saved->id,$user->id,'update',$before,$saved->getAttributes(),'HR activity updated');
                $savedActivityId=$saved->id;
                $this->notify($saved);
                return $oldPath;
            });
        } catch (Throwable $error) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $error;
        }
        // Earlier attachments remain private for the retained change history.

        $showDate=$data['status']==='Completed'?today()->toDateString():$data['opened_on'];
        return redirect()->to(route('dashboard', [
            'weekly_department'=>'hr_admin','hr_tab'=>$tab,'hr_period'=>$request->input('hr_period','week'),
            'hr_from'=>$showDate,'hr_to'=>max($showDate,(string)$request->input('hr_to',$showDate)),
            'hr_activity_focus'=>$savedActivityId,
        ]).'#hr-activity-table')->with('status', 'HR/Admin activity saved.');
    }

    public function show(Request $request,HrDashboardActivity $activity)
    {
        $user=$request->user();
        if(HrLiveRegisters::contributor($user))abort_unless(HrLiveRegisters::tabAllowed($user,$activity->tab==='operations'?'facilities':$activity->tab)&&in_array($user->id,[$activity->created_by,$activity->assigned_to],true),403);
         $participant=!in_array($activity->tab,['relations','welfare','compliance'],true)&&in_array($user->id,[(int)$activity->assigned_to,(int)$activity->staff_id,...array_map('intval',$activity->supporting_staff_ids??[])],true);
        abort_unless($user->isActive() && (HrLiveRegisters::full($user)||(HrLiveRegisters::contributor($user)&&$activity->created_by===$user->id)||$participant||($user->isLineManager()&&HrLiveRegisters::tabAllowed($user,$activity->tab)&&$activity->staff&&User::normalizeDepartmentKey($activity->staff->department)===User::normalizeDepartmentKey($user->department))),403);
        return response()->view('portal.hr-reporting.activity-detail',compact('activity'))->header('Cache-Control','private, no-store');
    }
    public function approve(Request $request,HrDashboardActivity $activity)
    {
        abort_unless($request->user()->isActive()&&$request->user()->isCvoOrSuperAdmin(),403);
        DB::transaction(function()use($request,$activity){
            $row=HrDashboardActivity::lockForUpdate()->findOrFail($activity->id);
            abort_if($row->created_by===$request->user()->id,403);
            $request->validate(['revision'=>'required|integer']);
            abort_unless($row->status==='Pending Approval'&&$row->revision===(int)$request->input('revision'),409,'This activity changed; refresh before approval.');
            $before=$row->getAttributes();$row->update(['status'=>'In Progress','approved_at'=>now(),'approved_by'=>$request->user()->id,'revision'=>$row->revision+1]);
            PerformanceWorkflow::record('hr_activity',$row->id,$request->user()->id,'approve',$before,$row->getAttributes(),'CVO approved the activity to proceed; completion is recorded separately.');
            $this->notify($row);
        });return back()->with('status','Activity approved to proceed. Completion remains a separate update.');
    }
    public function destroy(Request $request,HrDashboardActivity $activity)
    {
        abort_unless(HrLiveRegisters::canEdit($request->user())&&!HrLiveRegisters::contributor($request->user()),403);$request->validate(['revision'=>'required|integer']);
        DB::transaction(function()use($request,$activity){$row=HrDashboardActivity::lockForUpdate()->findOrFail($activity->id);abort_unless($row->revision===(int)$request->input('revision'),409,'Activity changed. Refresh before archiving.');$before=$row->getAttributes();$row->delete();PerformanceWorkflow::record('hr_activity',$row->id,$request->user()->id,'archive',$before,$row->getAttributes(),'HR activity archived with its history.');});
        return back()->with('status','Activity archived.');
    }
    private function notify(HrDashboardActivity $row): void
    {
        $url=route('portal.hr-dashboard.activities.show',$row);
        $recipients=array_values(array_unique(array_filter([$row->assigned_to,...($row->supporting_staff_ids??[])])));
        if(in_array($row->tab,['relations','welfare','compliance'],true))$recipients=User::whereIn('id',$recipients)->get()->filter(fn($u)=>HrLiveRegisters::full($u))->pluck('id')->all();
        NotificationService::sendToMany($recipients,'HR/Admin activity updated',$row->activity.' · '.$row->status.' · Deadline '.$row->deadline,$url);
        if($row->status==='Pending Approval')NotificationService::sendToMany(NotificationService::activeCvoApproverIds($row->created_by),'HR activity awaiting approval',$row->activity.' · Deadline '.$row->deadline,$url);
    }

    public function attachment(Request $request, HrDashboardActivity $activity)
    {
        $user = $request->user();
        if(HrLiveRegisters::contributor($user))abort_unless(HrLiveRegisters::tabAllowed($user,$activity->tab==='operations'?'facilities':$activity->tab)&&in_array($user->id,[$activity->created_by,$activity->assigned_to],true),404);
         $participant=!in_array($activity->tab,['relations','welfare','compliance'],true)&&in_array($user->id,[(int)$activity->assigned_to,(int)$activity->staff_id,...array_map('intval',$activity->supporting_staff_ids??[])],true);
        $legacyAdmin=User::normalizeDepartmentKey($user->department)==='hr_admin' && HrDashboard::canRead($user) && isset(HrDashboard::tabs($user)[$activity->tab]);
        abort_unless($user->isActive()&&(HrLiveRegisters::full($user)||(HrLiveRegisters::contributor($user)&&$activity->created_by===$user->id)||$participant||$legacyAdmin),404);
        $file = $activity->attachment;
        abort_unless($file && Storage::disk('local')->exists($file['path']), 404);
        return Storage::disk('local')->download($file['path'], $file['name'], [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
