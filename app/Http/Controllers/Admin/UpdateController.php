<?php
namespace App\Http\Controllers\Admin;
use App\Models\Task;
use Illuminate\Http\Request;

/** Older admin links retain the canonical task authorisation and completion review. */
class UpdateController extends \App\Http\Controllers\Portal\UpdateController
{
 public function destroy(Request $request, Task $update) { return app(\App\Http\Controllers\Portal\TaskController::class)->destroy($update,$request); }
}
