<?php
namespace App\Providers;

use App\Services\Sms\BeemGateway;
use App\Services\Sms\KilakonaGateway;
use App\Services\Sms\SmsGatewayInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->app->bind(SmsGatewayInterface::class, function () {
            return match (config('sms.driver')) {
                'beem'  => new BeemGateway(),
                default => new KilakonaGateway(),
            };
        });
    }

    public function boot(): void {
        //
    }
}
