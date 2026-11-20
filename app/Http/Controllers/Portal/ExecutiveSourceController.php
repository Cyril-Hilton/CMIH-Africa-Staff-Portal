<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PerformanceRegister;
use App\Models\User;
use App\Services\FinanceDashboard;
use App\Services\OperationsReporting;
use Illuminate\Http\Request;

class ExecutiveSourceController extends Controller
{
    public function show(Request $request, PerformanceRegister $register)
    {
        $u = $request->user();
        abort_unless($u->canViewExecutiveActivity() && $register->approved_at, 403);
        $dept = User::normalizeDepartmentKey($register->department);
        abort_unless(! in_array($dept, ['hr_admin', 'finance']) || $u->isCvoOrSuperAdmin() || $u->canAuditHrAndFinance(), 403);
        $data = $register->data ?? [];
        if ($dept === 'operations_projects') {
            $data = array_intersect_key($data, OperationsReporting::fields($register->kind, $u->isCvoOrSuperAdmin() || FinanceDashboard::canRead($u)));
        }
        $private = config('operations_tracker.private_fields', []);
        if (! $u->isCvoOrSuperAdmin() && ! FinanceDashboard::canRead($u)) {
            foreach ($private as $key) {
                unset($data[$key]);
            }
        }
        // Nested execution data has its own participant-safe projection; never echo arbitrary JSON.
        $data = collect($data)->reject(fn ($v, $k) => is_array($v) || str_starts_with($k, '_') || str_contains($k, 'salary') || str_contains($k, 'bank') || str_contains($k, 'password') || str_contains($k, 'token'))->all();

        return view('portal.executive.source-record', ['record' => $register, 'sourceData' => $data, 'returnTo' => $request->filled('return_to') ? TaskController::taskReturnUrl($request) : route('dashboard', ['exec_view' => 'projects'])]);
    }
}
