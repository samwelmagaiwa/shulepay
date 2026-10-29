<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Sms\BeemGateway;
use App\Services\Sms\KilakonaGateway;
use App\Services\Sms\SmsGatewayInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsGatewayInterface::class, function () {
            return match (config('sms.driver')) {
                'beem' => new BeemGateway,
                default => new KilakonaGateway,
            };
        });
    }

    public function boot(): void
    {
        // Superadmin passes every gate, policy and can() check.
        //
        // This is Laravel's documented hook for exactly this (Authorization ->
        // Intercepting Gate Checks), and Spatie's recommendation for a
        // super-admin role. Without it the bypass was written by hand in 13
        // controllers, which meant every new authorization check had to remember
        // to allow superadmin and silently locked it out when it did not.
        //
        // Returning null rather than false for everyone else is essential: false
        // would DENY the check outright and stop any real gate from running.
        Gate::before(fn (User $user, string $ability) => $user->isSuperAdmin() ? true : null);

        // No default rate limiter existed for the 'api' group at all — every
        // endpoint except login/2FA was completely unthrottled. 120/min per user
        // (falling back to IP for guests) is generous enough not to disrupt real
        // usage — draft auto-save alone fires roughly every 1.5s while a form is
        // being edited, ~40 requests/min from that feature alone — while still
        // bounding a compromised or malicious account from hammering the API.
        // Sign-in: 5 tries a minute per account+address, and 20 per address, so one attacker can neither
        // guess a password quickly nor lock everyone else out.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('login|'.strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by('login-ip|'.$request->ip()),
        ]);

        // Account activation by SMS: a code can be asked for 3 times in 10 minutes per account and address
        // (SMS costs money and must not be used to annoy someone), and guessed 5 times a minute.
        RateLimiter::for('activation-resend', fn (Request $request) => [
            Limit::perMinutes(10, 3)->by('act-resend|'.strtolower((string) $request->input('identifier')).'|'.$request->ip()),
            Limit::perMinutes(10, 15)->by('act-resend-ip|'.$request->ip()),
        ]);
        RateLimiter::for('activation-verify', fn (Request $request) => [
            Limit::perMinute(5)->by('act-verify|'.strtolower((string) $request->input('identifier')).'|'.$request->ip()),
            Limit::perMinute(20)->by('act-verify-ip|'.$request->ip()),
        ]);

        // Second sign-in step: limited per sign-in attempt (the challenge), plus a generous cap per address so
        // one school behind a shared connection is not locked out by its own staff.
        RateLimiter::for('twofa', fn (Request $request) => [
            Limit::perMinutes(15, 12)->by('twofa|'.substr((string) $request->input('challenge'), 0, 64)),
            Limit::perMinutes(15, 60)->by('twofa-ip|'.$request->ip()),
        ]);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
