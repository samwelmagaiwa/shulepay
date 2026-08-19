<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KilakonaGateway implements SmsGatewayInterface
{
    public function send(string $to, string $message): bool
    {
        $apiKey = config('sms.kilakona.api_key');
        $secretKey = config('sms.kilakona.secret_key');
        $senderId = config('sms.kilakona.sender_id');
        $apiUrl = config('sms.kilakona.api_url');

        if (! $apiKey || ! $secretKey) {
            Log::info("[SMS-stub] To:{$to} — {$message}");

            return true;
        }

        try {
            $response = Http::withHeaders([
                'api-key' => $apiKey,
                'api_secret' => $secretKey,
                'Content-Type' => 'application/json',
            ])->post($apiUrl, [
                'senderId' => $senderId,
                'messageType' => 'text',
                'contacts' => ltrim($to, '+'),
                'message' => $message,
            ]);

            if (! $response->successful()) {
                Log::error("[Kilakona SMS] HTTP {$response->status()}: ".$response->body());

                return false;
            }

            $body = $response->json();
            $success = ! empty($body['success']) || (isset($body['code']) && $body['code'] == 200);
            if (! $success) {
                Log::warning('[Kilakona SMS] Non-success response: '.$response->body());
            }

            return $success;
        } catch (\Throwable $e) {
            Log::error('[Kilakona SMS] Failed: '.$e->getMessage());

            return false;
        }
    }
}
