<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PortalCredentialsMail;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\PasswordPolicy;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $departments = ['hr_admin', 'finance', 'client_relations', 'operations_projects', 'brands_marketing', 'creatives'];

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',contact_email'],
            'phone' => ['required', 'string', 'max:32'],
            'department' => ['required', 'string', 'in:'.implode(',', $departments)],
            'job_title' => ['nullable', 'string', 'max:255'],
            'position_title' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => [
                'required',
                'date',
                'before_or_equal:' . now()->subYears(18)->toDateString(),
                'after_or_equal:' . now()->subYears(65)->toDateString()
            ],
            'start_date' => ['required', 'date'],
            'profile_photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,avif,bmp,gif,tif,tiff', 'max:4096'],
        ], [
            'date_of_birth.before_or_equal' => 'You must be at least 18 years old to register.',
            'date_of_birth.after_or_equal' => 'Age must be under 65 years old.',
        ]);

        $domain = config('app.company_email_domain', 'cmih.africa');
        $base = Str::of($request->name)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();
        $base = $base === '' ? 'user' : $base;

        $email = $this->uniqueCompanyEmail($base, $domain);
        $temporaryPassword = PasswordPolicy::generateTemporaryPassword();

        $photoPath = $request->file('profile_photo')->store('profiles', 'public');

        $dateOfBirth = $request->date_of_birth ? Carbon::parse($request->date_of_birth) : null;
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : null;
        $expiresAt = $startDate ? $startDate->copy()->addYear() : null;

        $staffId = User::generateStaffIdNumber();

        $user = User::create([
            'name' => $request->name,
            'email' => $email,
            'contact_email' => $request->contact_email,
            'phone' => $request->phone,
            'department' => $request->department,
            'job_title' => $request->job_title,
            'position_title' => $request->position_title,
            'staff_id_number' => $staffId,
            'date_of_birth' => $dateOfBirth?->toDateString(),
            'birthday_month' => $dateOfBirth?->month,
            'birthday_day' => $dateOfBirth?->day,
            'start_date' => $startDate?->toDateString(),
            'id_expires_at' => $expiresAt?->toDateString(),
            'profile_photo_path' => $photoPath,
            'password' => Hash::make($temporaryPassword),
            'access_role' => 'staff',
            'status' => 'pending',
            'must_reset_password' => true,
        ]);

        event(new Registered($user));

        NotificationService::sendApprovalNeededToMany(
            [],
            'New Staff Account Approval Needed',
            "{$user->name} created a new account and is waiting for approval.",
            route('admin.users'),
            $user->id
        );

        $emailSent = true;

        try {
            Mail::to($request->contact_email)->send(new PortalCredentialsMail($user, $temporaryPassword));
        } catch (Throwable $exception) {
            $emailSent = false;
            report($exception);
        }

        if ($user->idCardReady() && $user->contact_email) {
            try {
                Mail::to($user->contact_email)->send(new \App\Mail\StaffIdCardMail($user));
                $user->forceFill(['id_card_sent_at' => now()])->save();
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        if ($emailSent) {
            session()->flash('status', 'Welcome aboard! Your CMIH profile is ready for admin approval.');
        }

        return redirect()->route('login')
            ->with('generated_email', $email)
            ->with('email_sent', $emailSent);
    }

    private function uniqueCompanyEmail(string $base, string $domain): string
    {
        $email = $base.'@'.$domain;
        $suffix = 1;

        while (User::where('email', $email)->exists()) {
            $email = $base.$suffix.'@'.$domain;
            $suffix++;
        }

        return $email;
    }
}
