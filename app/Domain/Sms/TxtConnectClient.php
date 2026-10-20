<?php

namespace App\Domain\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TxtConnectClient
{
    public function send(string $to, string $sender, string $body): array
    {
        $key = config('services.txtconnect.key');
        $baseUrl = rtrim((string) config('services.txtconnect.base_url'), '/');
        if (! $key || ! $sender || ! str_starts_with($baseUrl, 'https://')) {
            throw new RuntimeException('TXTConnect is not configured. Set the HTTPS endpoint, API key and sender in .env.');
        }

        $response = Http::withToken($key)
            ->acceptJson()->asJson()->timeout(max(2, (int) config('services.txtconnect.timeout', 15)))
            ->post($baseUrl.'/sms/send', ['to' => $to, 'from' => $sender, 'unicode' => 'regular', 'sms' => $body]);
        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload) || ($payload['data']['status_code'] ?? null) !== '000') {
            throw new RuntimeException('TXTConnect request failed (HTTP '.$response->status().'): '.mb_substr((string) ($payload['msg'] ?? $payload['message'] ?? $response->body()), 0, 500));
        }

        return $payload;
    }
}
