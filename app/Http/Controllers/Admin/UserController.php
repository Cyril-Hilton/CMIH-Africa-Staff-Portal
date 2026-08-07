<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PortalCredentialsMail;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Illuminate\Support\Carbon;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $status = $request->query('status', 'active');
        $query = User::query()->internalStaff();
        if ($status === 'archived') {
            $query->where('status', 'archived');
        } else {
            $query->where('status', '!=', 'archived');
        }

        switch ($sort) {
            case 'name':
            case 'email':
            case 'access_role':
            case 'department':
            case 'status':
            case 'phone':
            case 'id_expires_at':
            case 'last_login_at':
                $query->orderBy($sort, $direction);
                break;
            default:
                $query->orderByDesc('created_at');
        }

        $users = $query->paginate(12)->withQueryString();

        return view('admin.users', compact('users'));
    }

    public function approve(User $user): RedirectResponse
    {
        $user->update(['status' => 'active']);

        return back()->with('status', 'User approved.');
    }

    public function suspend(User $user): RedirectResponse
    {
        $user->update(['status' => 'suspended']);

        return back()->with('status', 'User suspended.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'access_role' => ['required', 'string', 'in:staff,ops,admin,super_admin'],
        ]);

        $requestedRole = $validated['access_role'];
        $actor = $request->user();

        if ($user->access_role === 'super_admin' && $requestedRole !== 'super_admin') {
            return back()->withErrors(['access_role' => 'The super admin role cannot be changed.']);
        }

        if (! $actor->hasRole('super_admin') && in_array($requestedRole, ['admin', 'super_admin'], true)) {
            return back()->withErrors(['access_role' => 'Only a super admin can assign admin privileges.']);
        }

        $user->update(['access_role' => $validated['access_role']]);

        return back()->with('status', 'Role updated.');
    }

    public function updatePermissions(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if (! User::canEditUser($actor, $user)) {
            return back()->withErrors(['permissions' => 'You do not have permission to edit this user.']);
        }

        $validated = $request->validate([
            'job_level' => ['required', 'string', 'in:super_admin,manager,executive,promoter'],
            'permissions' => ['nullable', 'array'],
        ]);

        $user->update([
            'job_level' => $validated['job_level'],
            'permissions_matrix' => $validated['permissions'] ?? [],
        ]);

        return back()->with('status', 'Permissions matrix and job level updated.');
    }

    public function updateDepartment(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'department' => ['required', 'string', 'in:hr_admin,finance,client_relations,operations_projects,brands_marketing,creatives'],
        ]);

        $user->update(['department' => $validated['department']]);

        return back()->with('status', 'Department updated.');
    }

    public function updateIdExpiry(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'id_expires_at' => ['required', 'date'],
        ]);

        $expiresAt = Carbon::parse($validated['id_expires_at']);

        if ($user->start_date && $expiresAt->lt($user->start_date)) {
            return back()->withErrors(['id_expires_at' => 'ID expiry cannot be before the employment date.']);
        }

        $user->update([
            'id_expires_at' => $expiresAt->toDateString(),
        ]);

        return back()->with('status', 'ID expiry updated.');
    }

    public function resetCredentials(User $user): RedirectResponse
    {
        if (! $user->contact_email) {
            return back()->withErrors(['contact_email' => 'User does not have a contact email on file.']);
        }

        $temporaryPassword = PasswordPolicy::generateTemporaryPassword();

        $user->update([
            'password' => Hash::make($temporaryPassword),
            'must_reset_password' => true,
        ]);

        Mail::to($user->contact_email)->send(new PortalCredentialsMail($user, $temporaryPassword));

        return back()
            ->with('status', 'Credentials reset and emailed.')
            ->with('temporary_password', $temporaryPassword)
            ->with('temporary_password_user', $user->name)
            ->with('temporary_password_email', $user->email);
    }

    public function destroy(User $user): RedirectResponse
    {
        if (! request()->user()->hasRole(['admin', 'super_admin'])) {
            abort(403, 'Only admins can archive user accounts.');
        }

        if ($user->access_role === 'super_admin') {
            return back()->withErrors(['user' => 'The super admin account cannot be archived.']);
        }

        $user->update(['status' => 'archived']);

        return back()->with('status', 'User archived successfully.');
    }

    public function restore(User $user): RedirectResponse
    {
        if (! request()->user()->hasRole(['admin', 'super_admin'])) {
            abort(403, 'Only admins can restore archived user accounts.');
        }

        if ($user->status !== 'archived') {
            return back()->withErrors(['user' => 'This user is not archived.']);
        }

        $user->update(['status' => 'active']);

        return back()->with('status', 'User restored successfully.');
    }

    /**
     * Approve a user's pending profile level up / department change.
     * Allowed by Super Admins and full HR Managers.
     */
    public function approveProfileChange(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor->hasFullHrAccess()) {
            abort(403, 'Only an HR Manager or Super Admin can approve profile changes.');
        }

        // Prevent approving your own profile change
        if ($actor->id === $user->id) {
            return back()->withErrors(['profile' => 'You cannot approve your own profile change request.']);
        }

        // HR Managers cannot approve CVO-level profile changes
        $requestedTitle = $user->requested_position_title;
        if (! $actor->hasRole('super_admin') && in_array($requestedTitle, ['CVO'], true)) {
            return back()->withErrors(['profile' => 'Only a Super Admin can approve CVO-level changes.']);
        }

        $updates = [];
        $messages = [];

        if ($requestedTitle) {
            $updates['position_title'] = $requestedTitle;
            $messages[] = "Job Level changed to {$requestedTitle}";

            $managers = ['Manager', 'Department Head', 'CVO'];
            if (in_array($requestedTitle, $managers, true)) {
                $updates['job_level'] = 'manager';
                if ($user->access_role === 'staff') {
                    $updates['access_role'] = 'manager';
                }
            } else {
                $updates['job_level'] = 'executive';
            }
        }

        if ($user->requested_department) {
            $updates['department'] = $user->requested_department;
            $messages[] = "Department changed to {$user->requested_department}";
        }

        $updates['requested_position_title'] = null;
        $updates['requested_department'] = null;
        $updates['requested_change_at'] = null;

        $user->update($updates);

        $statusMsg = 'Approved profile changes for ' . $user->name . ': ' . implode(', ', $messages);

        return back()->with('status', $statusMsg);
    }

    /**
     * Reject a user's pending profile level up / department change.
     * Allowed by Super Admins and full HR Managers.
     */
    public function rejectProfileChange(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor->hasFullHrAccess()) {
            abort(403, 'Only an HR Manager or Super Admin can reject profile changes.');
        }

        // Prevent rejecting your own profile change
        if ($actor->id === $user->id) {
            return back()->withErrors(['profile' => 'You cannot reject your own profile change request.']);
        }

        $user->update([
            'requested_position_title' => null,
            'requested_department' => null,
            'requested_change_at' => null,
        ]);

        return back()->with('status', 'Rejected profile changes for ' . $user->name . '.');
    }
}
