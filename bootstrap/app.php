<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\AddSecurityHeaders::class,
            \App\Http\Middleware\RestrictSplitAppAccess::class,
            \App\Http\Middleware\UpdateLastSeen::class,
            \App\Http\Middleware\LogSlowRequests::class,
        ]);
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'role'   => \App\Http\Middleware\EnsureUserHasRole::class,
            'clocked_in' => \App\Http\Middleware\EnsureUserIsClockedIn::class,
            'identity_docs' => \App\Http\Middleware\EnsureIdentityDocumentComplete::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 419) {
                return back()->withInput()->with('status', 'Your session has expired. Please refresh the page and try again.');
            }
        });

        $exceptions->render(function (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unable to send email. The mail server took too long to respond. Please check your mail server configuration.'
                ], 500);
            }
            return back()->withInput()->withErrors([
                'email' => 'Unable to send email. The mail server took too long to respond. Please verify your mail configuration in .env.'
            ]);
        });
    })->create();
