<?php
namespace App\Http\Controllers\Portal;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
class HrAttendanceController extends Controller {
 public function __invoke(Request $request) {
  abort_unless($request->user()->hasFullHrAccess(),403);
  return view('portal.hr-reporting.attendance',app(\App\Services\HrAttendanceReport::class)->build($request->query()));
 }
}
