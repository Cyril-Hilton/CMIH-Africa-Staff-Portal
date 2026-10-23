<?php

namespace App\Services;

use App\Models\Appraisal;
use App\Models\Attendance;
use App\Models\LeaveApplication;
use App\Models\PettyCashClaim;
use App\Models\ProjectBudget;
use App\Models\SupplierInvoice;
use App\Models\User;

class ExecutiveDashboardSummary
{
    /** Private executive queues; never include this projection in shared department data. */
    public function build(User $user): array
    {
        if (!$user->isCvoOrSuperAdmin()) return [];
        return [
            ['label'=>'Financial Reviews','count'=>PettyCashClaim::whereIn('status',['Pending','Updated','Submitted to CVO','Finance Approved'])->count()+ProjectBudget::whereIn('status',['Submitted','Submitted to CVO','Finance Approved'])->count()+SupplierInvoice::whereIn('status',['Pending','Updated','Submitted to CVO','Finance Approved'])->count(),'route'=>'portal.finance','description'=>'Claims, budgets and invoices awaiting review.'],
            ['label'=>'Leave Approvals','count'=>LeaveApplication::whereIn('status',['pending_cvo','pending_manager_2','pending_hr_director'])->count(),'route'=>'portal.leaves','description'=>'Requests in the executive approval queue.'],
            ['label'=>'Appraisal Reviews','count'=>Appraisal::whereIn('status',['pending_cvo','pending_hr'])->count(),'route'=>'portal.appraisals.index','description'=>'Appraisals requiring HR or executive attention.'],
            ['label'=>'Staff Approvals','count'=>User::internalStaff()->where('status','pending')->count(),'route'=>'portal.people','description'=>'Pending staff accounts and onboarding decisions.'],
        ];
    }
    /** Actual recorded check-ins only; no IP geolocation or presumed office locations. */
    public function locations(User $user): array
    {
        if (!$user->isCvoOrSuperAdmin()) return [];
        return Attendance::query()
            ->with('user:id,name,department')
            ->whereHas('user', fn($query)=>$query->internalStaff())
            ->whereNotNull('clock_in_at')->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereBetween('latitude',[-90,90])->whereBetween('longitude',[-180,180])
            ->orderByDesc('clock_in_at')->orderByDesc('id')->limit(30)
            ->get(['id','user_id','clock_in_at','status','latitude','longitude'])
            ->map(fn($record)=>[
                'staff'=>$record->user->name,
                'department'=>config('performance.departments.'.User::normalizeDepartmentKey($record->user->department),$record->user->department),
                'at'=>$record->clock_in_at->timezone('Africa/Accra')->format('d M Y, H:i'),
                'status'=>$record->status,
                'latitude'=>(float)$record->latitude,
                'longitude'=>(float)$record->longitude,
            ])->all();
    }

}
