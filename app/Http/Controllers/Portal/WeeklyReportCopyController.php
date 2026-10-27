<?php
namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WeeklyReportingHistory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WeeklyReportCopyController extends Controller
{
    private function inputs(Request $request): array
    {
        $data=$request->validate(['department'=>['required', Rule::in(['brands_marketing','operations_projects','finance','hr_admin'])],'source_week'=>'required|date_format:Y-m-d','target_week'=>'nullable|date_format:Y-m-d']);
        $user=$request->user();
        abort_unless($user->isCvoOrSuperAdmin() || ($user->isEffectiveLineManager() && User::normalizeDepartmentKey($user->department)===$data['department']),403);
        return $data;
    }

    public function preview(Request $request, WeeklyReportingHistory $history)
    {
        $data=$this->inputs($request);
        $rows=$history->weekSources($data['department'],$data['source_week'])->get();
        $target=\Carbon\CarbonImmutable::parse($data['target_week']??today())->startOfWeek()->toDateString();
        $existing=\App\Models\WeeklyConsolidatedItem::whereIn('report_series',$rows->pluck('report_series')->filter())->whereDate('week_start',$target)->pluck('report_series');
        return response()->json(['total'=>$rows->count(),'source_week'=>\Carbon\CarbonImmutable::parse($data['source_week'])->startOfWeek()->toDateString(),'target_week'=>$target,
            'items'=>$rows->map(fn($r)=>['id'=>$r->id,'title'=>strip_tags($r->campaign_name?:$r->objective?:'Activity #'.$r->id),'client'=>$r->client_name,'status'=>$r->status,'existing'=>$r->report_series&&$existing->contains($r->report_series),'selected'=>!in_array($r->status,['Done','Deferred'])])->values()]);
    }

    public function store(Request $request, WeeklyReportingHistory $history)
    {
        $data=$this->inputs($request);
        $values=$request->validate(['target_week'=>'required|date_format:Y-m-d','items'=>'required|array|min:1|max:1000','items.*'=>'required|integer|distinct']);
        $result=$history->copyWeek($data['department'],$data['source_week'],$values['target_week'],$values['items'],$request->user()->id);
        $week=\Carbon\CarbonImmutable::parse($values['target_week'])->startOfWeek();
        $request->session()->flash('status', "{$result['created']} new weekly drafts created; {$result['existing']} existing entries kept unchanged.");
        return response()->json($result+['message'=>"{$result['created']} new weekly drafts created; {$result['existing']} existing entries kept unchanged.",'url'=>route('dashboard',['weekly_department'=>$data['department'],'weekly_from'=>$week->toDateString(),'weekly_to'=>$week->endOfWeek()->toDateString()]).'#staff-weekly-performance']);
    }
}
