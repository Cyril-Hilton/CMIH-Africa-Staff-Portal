<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\MerchandiserResetPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(Request $request): View
    {
        return view('auth.forgot-password', [
            'portal' => $this->portal($request),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => strtolower($request->email),
        ]);

        $request->validate([
            'email' => ['required', 'email'],
            'portal' => ['nullable', 'in:merchandisers'],
        ]);

        $user = User::whereRaw('LOWER(email) = ?', [$request->email])->first()
            ?: User::whereRaw('LOWER(contact_email) = ?', [$request->email])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => __(Password::INVALID_USER),
            ]);
        }

        $portal = $this->portal($request);

        $status = Password::sendResetLink(
            ['email' => $user->email],
            $portal === 'merchandisers'
                ? fn (User $user, string $token) => $user->notify(new MerchandiserResetPassword($token, $request->email))
                : null
        );

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }

    private function portal(Request $request): ?string
    {
        $portal = $request->route('portal') ?: $request->input('portal') ?: $request->query('portal');

        return $portal === 'merchandisers' ? 'merchandisers' : null;
    }
}
