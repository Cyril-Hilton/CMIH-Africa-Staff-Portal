<?php
namespace App\Http\Controllers\Portal;
use App\Http\Controllers\Controller;
use App\Models\PassOutRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
class PassOutController extends Controller {
    public function store(Request $request) {
        $user=$request->user();
        abort_unless($user->isActive() && User::internalStaff()->whereKey($user->id)->exists(),403);
        $v=$request->validate(['request_date'=>'required|date|after_or_equal:today','departure_time'=>'required|date_format:H:i|after_or_equal:13:00','reason'=>'required|string|min:5|max:2000','supervisor_id'=>'required|integer|exists:users,id','hod_id'=>'required|integer|exists:users,id']);
        $date=Carbon::parse($v['request_date']);
        if($date->isWeekend()) throw ValidationException::withMessages(['request_date'=>'Pass-out is available on weekdays only.']);
        foreach(['supervisor_id','hod_id'] as $field) {
            $reviewer=User::internalStaff()->where('status','active')->find($v[$field]);
            $sameDepartment=$reviewer && User::normalizeDepartmentKey($reviewer->department)===User::normalizeDepartmentKey($user->department);
            $valid=$reviewer && $reviewer->id!==$user->id && ($reviewer->isCvoOrSuperAdmin() || ($sameDepartment && ($reviewer->isLineManager() || ($field==='supervisor_id' && (int)$user->line_manager_id===$reviewer->id))));
            if(!$valid) throw ValidationException::withMessages([$field=>'Select an active supervisor / HOD in your department, or the CVO for a department head.']);
        }
        $passOut=DB::transaction(function()use($user,$v,$date){
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $key=$user->id.':'.$date->format('Y-m');
            if(PassOutRequest::where('reservation_key',$key)->exists())throw ValidationException::withMessages(['request_date'=>'You already have a pending or approved pass-out for this month. Only one half-day is allowed each calendar month.']);
            if(\App\Models\LeaveApplication::where('user_id',$user->id)->whereIn('status',['pending_manager','pending_cvo','pending_hr','approved'])->whereDate('start_date','<=',$date)->whereDate('end_date','>=',$date)->exists())throw ValidationException::withMessages(['request_date'=>'This date overlaps an existing leave request.']);
            return PassOutRequest::create([...$v,'user_id'=>$user->id,'reservation_key'=>$key,'history'=>[['action'=>'requested','actor_id'=>$user->id,'actor_name'=>$user->name,'at'=>now()->toIso8601String()]]]);
        });
        $this->notify($passOut);
        return redirect()->route('portal.leaves')->with('status','Half-day pass-out submitted to your supervisor.');
    }
    public function review(Request $request, PassOutRequest $passOut) {
        $v=$request->validate(['decision'=>'required|in:approve,reject,cancel','note'=>'nullable|string|max:2000']);
        DB::transaction(function()use($request,$passOut,$v){
            User::whereKey($passOut->user_id)->lockForUpdate()->firstOrFail();
            $row=PassOutRequest::lockForUpdate()->findOrFail($passOut->id);
            $user=$request->user();
            abort_unless(in_array($row->status,['pending_supervisor','pending_hod','pending_hr']),422,'This pass-out has already been finalized.');
            if($v['decision']==='cancel') {
                abort_unless($user->id===(int)$row->user_id,403);
                $changes=['status'=>'cancelled','reservation_key'=>null];
            } else {
                abort_if($user->id===(int)$row->user_id,403);
                abort_unless($user->status==='active' && ($row->status==='pending_hr' ? $user->hasFullHrAccess() : $user->id===$row->reviewerId() || $user->isActingLineManagerFor($row->reviewerId())),403);
                if($v['decision']==='reject') {
                    if(!trim($v['note']??'')) throw ValidationException::withMessages(['note'=>'Give the staff member a reason for rejection.']);
                    $changes=['status'=>'rejected','reservation_key'=>null];
                } else {
                    $next=match($row->status){'pending_supervisor'=>'pending_hod','pending_hod'=>'pending_hr','pending_hr'=>'approved'};
                    $changes=['status'=>$next];
                    if($next==='approved')$changes+=['hr_notes'=>$v['note']??null,'recorded_by'=>$user->id,'recorded_at'=>now()];
                }
            }
            $history=$row->history;
            $history[]=['action'=>$v['decision'],'from'=>$row->status,'to'=>$changes['status'],'actor_id'=>$user->id,'actor_name'=>$user->name,'at'=>now()->toIso8601String(),'note'=>$v['note']??null];
            $row->update($changes+['history'=>$history]);
        });
        $this->notify($passOut->fresh());
        return redirect()->route('portal.leaves')->with('status','Pass-out decision recorded.');
    }
    private function notify(PassOutRequest $row): void {
        $url=route('portal.leaves').'?passout_focus='.$row->id.'#pass-out';
        NotificationService::send($row->user_id,'Half-day pass-out',$row->statusLabel().' for '.$row->request_date->format('d M Y').'.',$url);
        $ids=$row->status==='pending_hr'?NotificationService::activeHrApproverIds($row->user_id):array_filter([$row->reviewerId()]);
        if($ids) NotificationService::sendToMany($ids,'Pass-out review needed',$row->user->name.' requested an afternoon half-day on '.$row->request_date->format('d M Y').'.',$url);
    }
}
