<?php
namespace App\Http\Controllers\Portal;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
class HrAttendanceController extends Controller {
 public function __invoke(Request $request) {
  abort_unless($request->user()->hasFullHrAccess() || $request->user()->canAuditHrAndFinance(),403);
  return response()->view('portal.hr-reporting.attendance', app(\App\Services\HrAttendanceReport::class)->build($request->query()))->header('Cache-Control', 'private, no-store');
 }
}
