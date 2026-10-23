<?php
namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use App\Services\DeliveryImpact;
use App\Services\PerformanceWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryImpactController extends Controller
{
    public function task(Request $request, Task $task) { return $this->save($request,$task,User::find($task->assigned_to),'task'); }
    public function commitment(Request $request, WeeklyConsolidatedItem $item) {
        abort_if($item->source_task_id,409,'Set impact points on the linked task.');
        return $this->save($request,$item,User::find($item->lead_staff_id),'commitment');
    }
    private function save(Request $request,$work,?User $owner,string $type) {
        abort_unless(DeliveryImpact::mayRate($request->user(),$owner),403);
        $data=$request->validate(['performance_weight'=>'required|integer|min:1|max:100','weight_reason'=>'required|string|min:10|max:2000']);
        DB::transaction(function() use($request,$work,$type,$data){
            $record=$work->newQuery()->lockForUpdate()->findOrFail($work->id);
            abort_if(($type==='task' && $record->completion_review_status==='approved') || ($type==='commitment' && in_array($record->workflow_state,['verified','closed'],true)),409,'Impact points are locked after final verification.');
            $before=$record->getAttributes();
            $record->forceFill($data+['weight_approved_by'=>$request->user()->id,'weight_approved_at'=>now()])->save();
            PerformanceWorkflow::record($type,$record->id,$request->user()->id,'set_impact',$before,$record->getAttributes(),$data['weight_reason']);
        });
        return back()->with('status','Impact points saved. Only manager-verified completion earns these points.');
    }
}
