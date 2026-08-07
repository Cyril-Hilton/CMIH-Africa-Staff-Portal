<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class OtpLoginController extends Controller
{
    public function create(): View
    {
        return view('auth.otp-request');
    }

    public function send(Request $request, SmsService $sms): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $user = User::where('phone', $validated['phone'])->first();

        if (! $user) {
            return back()->withErrors(['phone' => 'We could not find an account with that phone number.']);
        }

        if (! $user->isActive()) {
            return back()->withErrors(['phone' => 'Your account is pending approval or has been suspended.']);
        }

        $code = (string) random_int(100000, 999999);

        OtpCode::where('phone', $validated['phone'])->delete();

        OtpCode::create([
            'user_id' => $user->id,
            'phone' => $validated['phone'],
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        $sent = $sms->send($validated['phone'], "Your CMIH login code is {$code}. It expires in 10 minutes.");

        if (! $sent) {
            return back()->withErrors(['phone' => 'We could not send the OTP. Please contact an admin.']);
        }

        $request->session()->put('otp_phone', $validated['phone']);

        return redirect()->route('login.otp.verify')
            ->with('status', 'OTP sent. Check your phone.');
    }

    public function verifyForm(Request $request): View
    {
        if (! $request->session()->has('otp_phone')) {
            return view('auth.otp-request');
        }

        return view('auth.otp-verify', [
            'phone' => $request->session()->get('otp_phone'),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:10'],
        ]);

        $phone = $request->session()->get('otp_phone');

        if (! $phone) {
            return redirect()->route('login.otp')->withErrors(['phone' => 'Please request a new OTP.']);
        }

        $otp = OtpCode::where('phone', $phone)->latest()->first();

        if (! $otp || $otp->consumed_at || now()->greaterThan($otp->expires_at)) {
            return back()->withErrors(['code' => 'This OTP has expired. Please request a new one.']);
        }

        if (! Hash::check($request->code, $otp->code_hash)) {
            return back()->withErrors(['code' => 'The OTP code is incorrect.']);
        }

        $user = User::find($otp->user_id);

        if (! $user || ! $user->isActive()) {
            return back()->withErrors(['code' => 'This account is not active.']);
        }

        $otp->update(['consumed_at' => now()]);

        Auth::login($user);

        $request->session()->regenerate();

        $previousLoginAt = $user->last_login_at;

        $user->forceFill([
            'previous_login_at' => $previousLoginAt,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'last_login_user_agent' => substr((string) $request->userAgent(), 0, 255),
        ])->save();

        $request->session()->forget('otp_phone');

        if ($user->isMerchandiserSupervisor()) {
            return redirect()->route('merchandisers.admin.dashboard');
        }

        if ($user->isMerchandiserAccount()) {
            return redirect()->route('merchandisers.dashboard');
        }

        $redirectTo = $user->hasRole(['admin', 'super_admin'])
            ? route('admin.dashboard', absolute: false)
            : route('dashboard', absolute: false);

        return redirect()->intended($redirectTo);
    }
}
