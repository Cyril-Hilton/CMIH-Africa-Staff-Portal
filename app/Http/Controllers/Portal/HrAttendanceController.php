<?php
namespace App\Http\Controllers\Portal;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
class HrAttendanceController extends Controller {
 public function __invoke(Request $request) {
  $full=$request->user()->hasFullHrAccess() || $request->user()->canAuditHrAndFinance();
  $data=app(\App\Services\HrAttendanceReport::class)->build($request->query(),'page',$full?null:$request->user());
  return response()->view('portal.hr-reporting.attendance', $data+['attendanceSelf'=>!$full])->header('Cache-Control', 'private, no-store');
 }
}
