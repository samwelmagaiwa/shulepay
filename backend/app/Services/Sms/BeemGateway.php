<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BeemGateway implements SmsGatewayInterface
{
    public function send(string $to, string $message): bool
    {
        $apiKey = config('sms.beem.api_key');
        $secretKey = config('sms.beem.secret_key');
        $sender = config('sms.beem.sender_name');

        if (! $apiKey || ! $secretKey) {
            Log::info("[SMS-stub] To:{$to} — {$message}");

            return true;
        }

        try {
            $response = Http::withBasicAuth($apiKey, $secretKey)
                ->post('https://apisms.beem.africa/v1/send', [
                    'source_addr' => $sender,
                    'schedule_time' => '',
                    'encoding' => 0,
                    'message' => $message,
                    'recipients' => [['recipient_id' => 1, 'dest_addr' => $to]],
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('[Beem SMS] Failed: '.$e->getMessage());

            return false;
        }
    }
}
