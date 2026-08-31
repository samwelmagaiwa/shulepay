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
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
