<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Models\{Task,CreativeRequest};
class ProtectCreativeWorkflow {
 public function handle(Request $request,Closure $next){
  if(!$request->isMethodSafe()&&$request->user()&&Schema::hasTable('creative_requests')){
   $task=$request->route('task');$id=$task instanceof Task?$task->id:(is_scalar($task)&&ctype_digit((string)$task)?(int)$task:null);
   if($id && CreativeRequest::where('task_id',$id)->whereNotNull('planned_by')->exists())abort(403,'This task is governed by Creative Studio. Open its shared brief to update, review or reassign it.');
  }
  if(!$request->isMethodSafe()&&$request->user()&&Schema::hasTable('operations_handovers')) {
   $task=$request->route('task');$id=$task instanceof Task?$task->id:(is_scalar($task)&&ctype_digit((string)$task)?(int)$task:null);
   if($id&&\App\Models\OperationsHandover::where('task_id',$id)->exists())abort(403,'This task belongs to an Operations handover. Use the shared Handover Desk to update, accept or verify it.');
  }
  return $next($request);
 }
}
