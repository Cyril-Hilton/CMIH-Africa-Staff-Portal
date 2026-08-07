<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $isUserRoute = $request->route() && str_starts_with($request->route()->getName(), 'admin.users');
        $isMerchandiserAdminRoute = $request->route()
            && str_starts_with($request->route()->getName(), 'merchandisers.admin.');
        $isDeveloperBypass = in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah', 'curtis barnor', 'curtis banor'], true)
            && ! $user->hasRole(['admin', 'super_admin']);

        if (
            $user->hasRole($roles)
            || ($user->hasFullHrAccess() && $isUserRoute && ! $isDeveloperBypass)
            || ($isMerchandiserAdminRoute && $user->isMerchandiserPortalAdmin())
        ) {
            return $next($request);
        }

        abort(403);
    }
}
