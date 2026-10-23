<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user->isActive()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();

            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Your account is pending approval or has been suspended.',
            ]);
        }

        $previousLoginAt = $user->last_login_at;

        $user->forceFill([
            'previous_login_at' => $previousLoginAt,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'last_login_user_agent' => substr((string) $request->userAgent(), 0, 255),
        ])->save();

        if ($user->isMerchandiserSupervisor()) {
            return redirect()->route('merchandisers.admin.dashboard');
        }

        if ($user->isMerchandiserAccount()) {
            return redirect()->route('merchandisers.dashboard');
        }

        // Executives start on the same operational dashboard as the sidebar.
        // Administrative tools remain available through Admin Overview.
        if ($user->isCvoOrSuperAdmin()) {
            $request->session()->forget('url.intended');

            return redirect()->route('dashboard');
        }

        // Background polling must not become the landing page after sign-in.
        if (parse_url((string) $request->session()->get('url.intended'), PHP_URL_PATH) === '/portal/dashboard/live') {
            $request->session()->forget('url.intended');
        }

        $redirectTo = $user->hasRole(['admin'])
            ? route('admin.dashboard', absolute: false)
            : route('dashboard', absolute: false);

        return redirect()->intended($redirectTo);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
