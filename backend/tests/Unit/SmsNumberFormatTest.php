<?php

namespace Tests\Unit;

use App\Services\Sms\SmsGatewayInterface;
use App\Services\Sms\SmsService;
use PHPUnit\Framework\TestCase;

class SmsNumberFormatTest extends TestCase
{
    public function test_every_way_of_writing_a_tanzanian_number_gives_the_same_international_form(): void
    {
        $sms = new SmsService($this->createStub(SmsGatewayInterface::class));

        foreach (['0743519104', '743519104', '255743519104', '+255743519104', '+255 743 519 104', '0743 519 104'] as $input) {
            $this->assertSame('+255743519104', $sms->normaliseTz($input), $input);
        }
        $this->assertSame('+254712345678', $sms->normaliseTz('+254712345678'), 'another country is left alone');
    }
}
