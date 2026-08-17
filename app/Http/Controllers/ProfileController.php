<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\IdentityVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Display a printable staff ID card.
     */
    public function idCard(Request $request): View
    {
        return view('portal.id-card', [
            'user' => $request->user(),
        ]);
    }

    public function photo(Request $request, User $user): BinaryFileResponse
    {
        abort_unless($request->user()?->isActive(), 403);

        $path = \Illuminate\Support\Str::of((string) $user->profile_photo_path)
            ->ltrim('/')
            ->replaceStart('storage/', '')
            ->toString();

        abort_if($path === '' || ! Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Content-Type' => Storage::disk('public')->mimeType($path) ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['mute_sounds'] = $request->boolean('mute_sounds');
        $user = $request->user();
        $hadPendingProfileApproval = filled($user->requested_department) || filled($user->requested_position_title);

        // Intercept department and job level changes for standard users
        if (! $user->hasFullHrAccess()) {
            if (isset($data['department'])) {
                if ($user->department && $data['department'] !== $user->department) {
                    $user->requested_department = $data['department'];
                    $user->requested_change_at = now();
                    // Keep original value on immediate save
                    $data['department'] = $user->department;
                } else {
                    $user->requested_department = null;
                }
            }

            if (isset($data['position_title'])) {
                if ($user->position_title && $data['position_title'] !== $user->position_title) {
                    $user->requested_position_title = $data['position_title'];
                    $user->requested_change_at = now();
                    // Keep original value on immediate save
                    $data['position_title'] = $user->position_title;
                } else {
                    $user->requested_position_title = null;
                }
            }

            if ($user->requested_department === null && $user->requested_position_title === null) {
                $user->requested_change_at = null;
            }
        }

        if (array_key_exists('date_of_birth', $data)) {
            if ($data['date_of_birth']) {
                $dateOfBirth = Carbon::parse($data['date_of_birth']);
                $data['birthday_month'] = $dateOfBirth->month;
                $data['birthday_day'] = $dateOfBirth->day;
            } else {
                $data['birthday_month'] = null;
                $data['birthday_day'] = null;
            }
        }

        if (empty($user->id_expires_at) && !empty($data['start_date'])) {
            $data['id_expires_at'] = Carbon::parse($data['start_date'])->addYear()->toDateString();
        }

        if (! $user->staff_id_number) {
            $hasDob = ! empty($data['date_of_birth']) || $user->date_of_birth;
            $hasStart = ! empty($data['start_date']) || $user->start_date;

            if ($hasDob && $hasStart) {
                $data['staff_id_number'] = User::generateStaffIdNumber();
            }
        }

        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('profiles', 'public');

            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $data['profile_photo_path'] = $path;
        }

        foreach ([
            'national_id_front' => 'national_id_front_path',
            'national_id_back' => 'national_id_back_path',
            'passport_document' => 'passport_photo_path',
        ] as $input => $column) {
            if (! $request->hasFile($input)) {
                continue;
            }

            $path = $request->file($input)->store("identity-documents/{$user->id}", 'local');

            if ($user->{$column}) {
                Storage::disk('local')->delete($user->{$column});
                Storage::disk('public')->delete($user->{$column});
            }

            $data[$column] = $path;
        }

        if (filled($data['nationality_code'] ?? null)) {
            $data['nationality_code'] = strtoupper($data['nationality_code']);
            $data['national_id_type'] = User::nationalIdLabelFor($data['nationality_code']);
        }

        unset(
            $data['profile_photo'],
            $data['national_id_front'],
            $data['national_id_back'],
            $data['passport_document']
        );

        $user->fill($data);

        $needsProfileApprovalNotification = ! $user->hasFullHrAccess()
            && (filled($user->requested_department) || filled($user->requested_position_title))
            && (
                ! $hadPendingProfileApproval
                || $user->isDirty('requested_department')
                || $user->isDirty('requested_position_title')
                || $user->isDirty('requested_change_at')
            );

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $idCardFields = [
            'name',
            'job_title',
            'position_title',
            'staff_id_number',
            'department',
            'date_of_birth',
            'start_date',
            'id_expires_at',
            'profile_photo_path',
        ];

        $cardChanged = $user->wasChanged($idCardFields);

        if ($user->idCardReady() && $user->contact_email && ($cardChanged || ! $user->id_card_sent_at)) {
            try {
                Mail::to($user->contact_email)->send(new \App\Mail\StaffIdCardMail($user));
                $user->forceFill(['id_card_sent_at' => now()])->save();
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if ($needsProfileApprovalNotification) {
            NotificationService::sendApprovalNeededToMany(
                NotificationService::activeHrApproverIds($user->id),
                'Profile Change Approval Needed',
                "{$user->name} requested profile changes that need approval.",
                route('admin.users'),
                $user->id
            );
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->access_role === 'super_admin';
    }

    private function isDeveloper(User $user): bool
    {
        return in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah', 'curtis barnor', 'curtis banor'], true);
    }
}
