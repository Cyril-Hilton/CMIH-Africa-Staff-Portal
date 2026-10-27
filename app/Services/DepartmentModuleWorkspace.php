<?php

namespace App\Services;

use App\Models\{PerformanceRegister, User};
use Illuminate\Http\Request;

/** Department entry and public presentation read the same records, never copied ledgers. */
class DepartmentModuleWorkspace
{
    public function build(Request $request, string $department): array
    {
        $dates = $request->validate(['weekly_from'=>'nullable|date_format:Y-m-d','weekly_to'=>'nullable|date_format:Y-m-d|after_or_equal:weekly_from','register_kind'=>'nullable|string|max:50','module_view'=>'nullable|string|max:40','module_q'=>'nullable|string|max:150']);
        $from = $dates['weekly_from'] ?? now()->startOfWeek()->toDateString();
        $to = $dates['weekly_to'] ?? now()->endOfWeek()->toDateString();
        $request->query->add(['weekly_from'=>$from,'weekly_to'=>$to]);
        $schemas = config('department_reporting.'.$department, []);
        $kind = $dates['register_kind'] ?? array_key_first($schemas);
        abort_unless(isset($schemas[$kind]), 422, 'Choose a reporting category for this department.');
        $registers = PerformanceAccess::scope(PerformanceRegister::query(), $request->user(), 'owner_id')
            ->where('department',$department)->where('kind',$kind)->whereDate('period_start','<=',$to)->whereDate('period_end','>=',$from)
            ->when($dates['module_q']??null,fn($q,$search)=>$q->where(fn($q)=>$q->where('title','like','%'.$search.'%')->orWhere('reference','like','%'.$search.'%')))
            ->orderByDesc('period_start')->orderByDesc('id')->paginate(10,['*'],'module_register_page')->withQueryString()->appends(['module_view'=>'registers']);
        return ['moduleDepartment'=>$department,'moduleFrom'=>$from,'moduleTo'=>$to,'moduleSchemas'=>$schemas,'moduleKind'=>$kind,'moduleRegisters'=>$registers,
            'moduleManages'=>PerformanceAccess::manages($request->user(),$department),
            'moduleStaff'=>User::internalStaff()->where('status','active')->whereIn('department',User::departmentAliases($department))->orderBy('name')->get(),
            'weeklyDepartmentFilter'=>$department,'sharedWeeklyPerformance'=>app(SharedWeeklyPerformance::class)->build($department,$from,$to),
            'weeklyDepartmentTables'=>app(WeeklyDepartmentTables::class)->build($department,$from,$to)];
    }
}
