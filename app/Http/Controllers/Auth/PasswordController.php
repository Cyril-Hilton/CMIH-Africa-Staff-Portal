<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => PasswordPolicy::confirmedRules(),
        ]);

        $user = $request->user();

        $user->update([
            'password' => Hash::make($validated['password']),
            'must_reset_password' => false,
        ]);

        Auth::login($user);

        return back()->with('status', 'password-updated');
    }
}
