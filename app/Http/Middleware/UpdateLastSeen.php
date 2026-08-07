<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Updates the authenticated user's last_seen_at timestamp on every request.
 * This powers the online presence indicator in CMIH Messenger.
 */
class UpdateLastSeen
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            // Only write to DB once per minute to avoid hammering it on every request
            if (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinute())) {
                $user->timestamps = false; // don't touch updated_at
                $user->last_seen_at = now();
                $user->save();
                $user->timestamps = true;
            }
        }

        return $next($request);
    }
}
