<?php

use App\Http\Middleware\EnforcePasswordChange;
use App\Http\Middleware\EnsureDashboardUnlocked;
use App\Http\Middleware\EnsureParentOwnsStudent;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetActiveSchool;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(HandleCors::class);
        $middleware->throttleApi();

        // Behind the host proxy and the frontend container, every request used to arrive from a Docker-internal
        // address, so per-IP limits and login history were shared by everybody. Trust forwarded headers only from
        // private ranges; the client address is then the first untrusted hop in X-Forwarded-For (a spoofed header
        // from outside is ignored).
        $middleware->trustProxies(
            at: ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );

        // This backend has no `login` route. Laravel builds the redirect target
        // while constructing the AuthenticationException, so route('login') threw
        // RouteNotFoundException first and the guest saw a 500 instead of the JSON
        // 401 configured below. Returning null skips the redirect entirely.
        $middleware->redirectGuestsTo(fn () => null);
        // Before anything renders: printed documents and validation messages
        // follow the language the user picked in the app.
        $middleware->appendToGroup('api', SetLocale::class);
        $middleware->appendToGroup('api', SetActiveSchool::class);
        $middleware->appendToGroup('api', EnsureUserIsActive::class);
        $middleware->appendToGroup('api', EnforcePasswordChange::class);
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'parent.owns_student' => EnsureParentOwnsStudent::class,
            'dashboard.unlocked' => EnsureDashboardUnlocked::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always return JSON 401 for API routes — never redirect to "login"
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });
    })->create();
