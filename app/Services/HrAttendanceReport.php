<?php
namespace App\Services;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Carbon;
class HrAttendanceReport {
 public function build(array $filters=[], string $pageName='page'):array {
        $v = \Illuminate\Support\Facades\Validator::make($filters, ['period'=>'nullable|in:weekly,monthly,yearly','date'=>'nullable|date','q'=>'nullable|string|max:100','department'=>'nullable|string|max:100','punctuality'=>'nullable|in:all,late,on_time','sort'=>'nullable|in:name,newest,oldest','direction'=>'nullable|in:asc,desc'])->validate();
        $period=$v['period']??'weekly'; $anchor=Carbon::parse($v['date']??today());
        $from=match($period){'monthly'=>$anchor->copy()->startOfMonth(),'yearly'=>$anchor->copy()->startOfYear(),default=>$anchor->copy()->startOfWeek()};
        $to=match($period){'monthly'=>$from->copy()->endOfMonth(),'yearly'=>$from->copy()->endOfYear(),default=>$from->copy()->endOfWeek()};
        $base=Attendance::query()->whereBetween('clock_in_at',[$from,$to])->whereHas('user',function($q)use($v){$q->when($v['q']??null,fn($q,$term)=>$q->where('name','like','%'.$term.'%'))->when($v['department']??null,fn($q,$dept)=>$q->where('department',$dept));});
        if(($v['punctuality']??'all')==='late') $base->whereTime('clock_in_at','>','09:00:00');
        if(($v['punctuality']??'all')==='on_time') $base->whereTime('clock_in_at','<=','09:00:00');
        $summary=(clone $base)->with('user:id,name')->get()->groupBy('user_id')->map(function($rows){$onTime=$rows->filter(fn($r)=>$r->clock_in_at->lte($r->clock_in_at->copy()->setTime(9,0)));return ['name'=>$rows->first()->user?->name??'Former staff','sessions'=>$rows->count(),'punctuality'=>round(100*$onTime->count()/$rows->count(),1),'late'=>$rows->count()-$onTime->count(),'overtime'=>$rows->sum('overtime_minutes')];})->sortBy('name');
        $sort=$v['sort']??'name'; $direction=$v['direction']??'asc';
        if($sort==='name') $base->orderBy(User::select('name')->whereColumn('users.id','attendance.user_id'),$direction)->orderByDesc('clock_in_at');
        else $base->orderBy('clock_in_at',$sort==='newest'?'desc':'asc');
        $logs=$base->with('user:id,name,department')->paginate(30,['*'],$pageName)->withQueryString();
        $departments=User::whereNotNull('department')->distinct()->orderBy('department')->pluck('department');
        return compact('logs','summary','departments','period','from','to');
 }
}
