<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $viewer = $request->user();
        
        // Fetch all active users for birthdays/anniversaries
        $teamAll = User::internalStaff()->where('status', 'active')->orderBy('name')->get();

        // For the directory list/table
        $teamQuery = User::internalStaff()->where('status', 'active');
        if (!$viewer || $viewer->access_role !== 'super_admin') {
            $teamQuery->where(function ($q) {
                $q->where('access_role', '!=', 'super_admin')
                  ->orWhere('name', 'like', '%Cyril Hilton%');
            });
        }
        $team = $teamQuery->orderBy('name')->paginate(12);

        // For the Organogram pyramid: active users, excluding super_admins except Cyril Hilton
        $organogramUsers = User::internalStaff()
            ->where('status', 'active')
            ->get()
            ->filter(function (User $u) {
                if ($u->access_role === 'super_admin') {
                    return in_array(strtolower(trim($u->name)), ['cyril hilton', 'cyril hilton wemegah'], true);
                }
                return true;
            })
            ->values();

        $month = Carbon::now()->month;

        $today = Carbon::today();
        $birthdayWindowEnd = $today->copy()->addDays(30);
        $birthdays = collect();
        if ($viewer && $viewer->access_role === 'super_admin') {
            $birthdays = $teamAll->filter(function (User $user) use ($today, $birthdayWindowEnd) {
                $nextBirthday = $user->nextBirthdayDate($today);

                if (! $nextBirthday) {
                    return false;
                }

                return $nextBirthday->betweenIncluded($today, $birthdayWindowEnd);
            })->sortBy(function (User $user) use ($today) {
                return $user->nextBirthdayDate($today)?->timestamp ?? PHP_INT_MAX;
            });
        }

        $anniversaries = $teamAll->filter(function (User $user) use ($month) {
            return $user->start_date && $user->start_date->month === $month;
        })->sortBy(function (User $user) {
            return $user->start_date->day;
        });

        return view('portal.directory', compact('team', 'birthdays', 'anniversaries', 'organogramUsers'));
    }

    /**
     * Update privileges for a staff member.
     */
    public function updatePrivileges(Request $request, User $user)
    {
        $viewer = $request->user();

        if (!User::canEditUser($viewer, $user)) {
            abort(403, '🔒 Access Denied. You do not have permission to edit this user\'s privileges.');
        }

        $validated = $request->validate([
            'access_role' => ['required', 'string', 'in:staff,manager,admin,super_admin'],
            'position_title' => ['required', 'string', 'in:Support Staff,Executive,Senior Executive,Assistant Manager,Manager,Department Head,CVO'],
            'department' => ['required', 'string', 'in:hr_admin,finance,client_relations,operations_projects,brands_marketing,creatives'],
        ]);

        // Check if trying to promote to admin or super_admin
        if (in_array($validated['access_role'], ['admin', 'super_admin'], true) && !$viewer->hasRole('super_admin')) {
             return back()->withErrors(['access_role' => 'Only Super Admin can assign Admin or Super Admin privileges.']);
        }

        $updates = [
            'access_role' => $validated['access_role'],
            'position_title' => $validated['position_title'],
            'department' => $validated['department'],
        ];

        // Map requested position title to default system access_role & job_level
        $managers = ['Manager', 'Department Head', 'CVO'];
        if (in_array($validated['position_title'], $managers, true)) {
            $updates['job_level'] = 'manager';
            if ($validated['access_role'] === 'staff') {
                $updates['access_role'] = 'manager';
            }
        } else {
            $updates['job_level'] = 'executive';
        }

        // HR Managers, CVO, and Super Admin can enter salary and upload contract/job description
        $isHrOrSuperOrCvo = $viewer->hasFullHrAccess();

        if ($isHrOrSuperOrCvo) {
            $request->validate([
                'salary' => ['nullable', 'numeric', 'min:0'],
                'payroll_deductions' => ['nullable', 'numeric', 'min:0'],
                'payroll_rewards_bonus' => ['nullable', 'numeric', 'min:0'],
                'payroll_notes' => ['nullable', 'string', 'max:2000'],
                'contract' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,png', 'max:10240'],
                'job_description' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,png', 'max:10240'],
            ]);

            if ($request->has('salary')) {
                $updates['salary'] = $request->input('salary');
            }

            foreach (['payroll_deductions', 'payroll_rewards_bonus', 'payroll_notes'] as $field) {
                if ($request->has($field)) {
                    $updates[$field] = filled($request->input($field)) ? $request->input($field) : null;
                }
            }

            if ($request->hasFile('contract')) {
                if ($user->contract_path) {
                    Storage::disk('local')->delete($user->contract_path);
                    Storage::disk('public')->delete($user->contract_path);
                }

                $updates['contract_path'] = $request->file('contract')->store('contracts', 'local');
            }

            if ($request->hasFile('job_description')) {
                if ($user->job_description_path) {
                    Storage::disk('local')->delete($user->job_description_path);
                    Storage::disk('public')->delete($user->job_description_path);
                }

                $updates['job_description_path'] = $request->file('job_description')->store('job_descriptions', 'local');
            }
        }

        $user->update($updates);

        return back()->with('status', "Privileges updated successfully for {$user->name}.");
    }
}
