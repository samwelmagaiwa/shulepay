<?php

namespace Tests\Feature;

use App\Services\Sms\SmsGatewayInterface;
use App\Services\Sms\SmsService;
use Tests\TestCase;

/** Security codes are sent for real while the general SMS switch (receipts, alerts) stays off. */
class SmsSecurityCodeSwitchTest extends TestCase
{
    /** @var list<bool> the general switch as the gateway saw it, per message */
    private array $seen = [];

    private function service(): SmsService
    {
        $seen = &$this->seen;

        return new SmsService(new class($seen) implements SmsGatewayInterface
        {
            public function __construct(private array &$seen) {}

            public function send(string $to, string $message): bool
            {
                $this->seen[] = (bool) config('sms.enabled');

                return true;
            }
        });
    }

    public function test_a_security_code_is_sent_while_ordinary_sms_stays_off(): void
    {
        config(['sms.enabled' => false, 'sms.codes_enabled' => true]);
        $sms = $this->service();

        $sms->sendSecurityCode('0743519104', 'code');
        $sms->send('0743519104', 'receipt');

        $this->assertSame([true, false], $this->seen, 'code goes out for real, the receipt still sees SMS switched off');
        $this->assertFalse(config('sms.enabled'), 'the general switch is restored afterwards');
    }

    public function test_the_code_switch_can_be_turned_off_too(): void
    {
        config(['sms.enabled' => false, 'sms.codes_enabled' => false]);

        $this->service()->sendSecurityCode('0743519104', 'code');

        $this->assertSame([false], $this->seen);
    }
}
