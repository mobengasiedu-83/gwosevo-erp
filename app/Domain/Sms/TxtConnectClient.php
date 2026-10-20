<?php

namespace App\Domain\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        // The ERP keeps E.164 numbers (including a leading +) for consent matching;
        // TXTConnect expects the same digits without that prefix character.
        $providerNumber = str_starts_with($to, '+') ? substr($to, 1) : $to;
        $unicode = (string) config('services.txtconnect.unicode', '0');
        if (! in_array($unicode, ['0', '1'], true)) {
            throw new RuntimeException('TXTCONNECT_UNICODE must be set to 0 or 1.');
        }

        // Match TXTConnect's Postman example, which sends this flag as "0" or "1".
        $requestData = ['to' => $providerNumber, 'from' => $sender, 'unicode' => $unicode, 'sms' => $body];
        Log::channel('sms')->info('TXTConnect API request sent.', [
            'endpoint' => $baseUrl.'/sms/send',
            'http_method' => 'POST',
            'authentication' => 'Bearer token configured; token omitted',
            'request' => $requestData,
        ]);

        try {
            $response = Http::withToken($key)
                ->acceptJson()->asJson()->timeout(max(2, (int) config('services.txtconnect.timeout', 15)))
                ->post($baseUrl.'/sms/send', $requestData);
        } catch (\Throwable $exception) {
            Log::channel('sms')->error('TXTConnect API request failed before receiving a response.', [
                'endpoint' => $baseUrl.'/sms/send',
                'exception' => $exception::class,
                'reason' => mb_substr(str_replace($key, '[redacted]', $exception->getMessage()), 0, 1000),
            ]);
            throw $exception;
        }
        $payload = $response->json();

        $responseBody = is_array($payload)
            ? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $response->body();
        $responseBody = is_string($responseBody) ? $responseBody : '[unavailable]';
        if (is_string($key) && $key !== '') {
            $responseBody = str_replace($key, '[redacted]', $responseBody);
        }
        $responseBody = preg_replace('/("?(?:password|passwd|token|authorization|api[_-]?key)"?\s*[:=]\s*"?)[^",}\s]+/i', '$1[redacted]', $responseBody) ?? '[unavailable]';
        Log::channel('sms')->info('TXTConnect API response received.', [
            'http_status' => $response->status(),
            'successful_http_status' => $response->successful(),
            'response' => $responseBody,
        ]);

        if (! $response->successful() || ! is_array($payload) || ($payload['data']['status_code'] ?? null) !== '000') {
            throw new RuntimeException('TXTConnect request failed (HTTP '.$response->status().'): '.mb_substr((string) ($payload['msg'] ?? $payload['message'] ?? $response->body()), 0, 500));
        }

        return $payload;
    }
}
