<?php

namespace App\Providers;

use App\Services\Sms\BeemGateway;
use App\Services\Sms\KilakonaGateway;
use App\Services\Sms\SmsGatewayInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
