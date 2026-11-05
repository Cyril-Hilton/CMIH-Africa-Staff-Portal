<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\HrDashboardActivity;
use App\Models\User;
use App\Services\Creative\CreativeUploadScreening;
use App\Services\HrDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class HrDashboardActivityController extends Controller
{
    public function save(Request $request, ?HrDashboardActivity $activity = null)
    {
        $user = $request->user();
        abort_unless(HrDashboard::canAccess($user), 403);
        $tabs = HrDashboard::tabs($user);
        $tab = $activity?->tab ?? $request->input('tab');
        abort_unless(is_string($tab) && isset($tabs[$tab]), 403);
        if ($activity) {
            abort_unless($user->hasFullHrAccess() || in_array($user->id, [$activity->created_by, $activity->assigned_to]), 403);
        }

        $data = $request->validate([
            'category' => ['required', Rule::in($tabs[$tab][1])],
            'activity' => 'required|string|max:255',
            'staff_id' => 'nullable|integer',
            'department' => ['nullable', Rule::in(array_keys(config('performance.departments')))],
            'opened_on' => 'required|date_format:Y-m-d',
            'deadline' => 'nullable|date_format:Y-m-d|after_or_equal:opened_on',
            'assigned_to' => 'required|integer',
            'status' => ['required', Rule::in(HrDashboardActivity::STATUSES)],
            'remarks' => 'nullable|string|max:6000',
            'attachment' => 'nullable|file|mimes:pdf,docx,xlsx,pptx,txt,jpg,jpeg,png,webp|max:10240',
            'revision' => $activity ? 'required|integer|min:1' : 'nullable|integer',
        ]);
        if (!HrDashboard::team()->whereKey($data['assigned_to'])->where(fn ($q) => $q
            ->where('status', 'active')->orWhere('id', $activity?->assigned_to))->exists()) {
            throw ValidationException::withMessages(['assigned_to' => 'Choose a member of the HR/Admin team.']);
        }
        if (!empty($data['staff_id']) && !User::internalStaff()->whereKey($data['staff_id'])->exists()) {
            throw ValidationException::withMessages(['staff_id' => 'Choose an internal staff member.']);
        }

        $file = $request->file('attachment');
        $revision = $data['revision'] ?? null;
        unset($data['attachment'], $data['revision']);
        $newPath = null;
        if ($file) {
            app(CreativeUploadScreening::class)->check($file);
            $newPath = $file->store('hr-dashboard-activities', 'local');
            if (!$newPath) {
                throw new RuntimeException('The attachment could not be stored.');
            }
            $data['attachment'] = ['path' => $newPath, 'name' => basename($file->getClientOriginalName())];
        }
        try {
            $oldPath = DB::transaction(function () use ($data, $activity, $revision, $tab, $user) {
                if (!$activity) {
                    HrDashboardActivity::create($data + ['tab' => $tab, 'created_by' => $user->id]);
                    return null;
                }
                $saved = HrDashboardActivity::query()->lockForUpdate()->findOrFail($activity->id);
                abort_if((int) $saved->revision !== (int) $revision, 409, 'This activity changed. Refresh it before editing.');
                $oldPath = $saved->attachment['path'] ?? null;
                $saved->update($data + ['revision' => $saved->revision + 1]);
                return $oldPath;
            });
        } catch (Throwable $error) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $error;
        }
        if ($newPath && $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return redirect()->to(route('dashboard', $request->only(['hr_period', 'hr_from', 'hr_to', 'hr_owner', 'hr_q', 'hr_category', 'hr_status', 'hr_sort', 'hr_direction']) + [
            'weekly_department' => 'hr_admin', 'hr_tab' => $tab,
        ]).'#staff-weekly-performance')->with('status', 'HR/Admin activity saved.');
    }

    public function attachment(Request $request, HrDashboardActivity $activity)
    {
        $user = $request->user();
        abort_unless(HrDashboard::canAccess($user) && isset(HrDashboard::tabs($user)[$activity->tab]), 404);
        $file = $activity->attachment;
        abort_unless($file && Storage::disk('local')->exists($file['path']), 404);
        return Storage::disk('local')->download($file['path'], $file['name'], [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
