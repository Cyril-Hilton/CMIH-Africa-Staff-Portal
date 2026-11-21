<?php
namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;

/** Retained update URLs use the same records, permissions and review flow as Tasks. */
class UpdateController extends Controller
{
 public function index(Request $request) { return app(TaskController::class)->index($request); }
 public function store(Request $request) { $this->normaliseLegacyFields($request); return app(TaskController::class)->store($request); }
 public function edit(Request $request, Task $update) { return app(TaskController::class)->edit($update,$request); }
 public function update(Request $request, Task $update) { $this->normaliseLegacyFields($request); return app(TaskController::class)->update($request,$update); }
 protected function normaliseLegacyFields(Request $request): void
 {
  if($request->has('summary')&&!$request->has('details'))$request->merge(['details'=>trim($request->input('summary','').($request->filled('notes')?"\nNotes: ".$request->input('notes'):''))]);
  $status=match($request->input('status')){'on_track','at_risk'=>'In Progress','delayed'=>'Overdue','completed'=>'Completed',default=>$request->input('status')};
  if($status!==null)$request->merge(['status'=>$status]);
 }
}
