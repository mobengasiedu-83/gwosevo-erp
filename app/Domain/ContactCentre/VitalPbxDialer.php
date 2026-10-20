<?php

namespace App\Domain\ContactCentre;

use App\Models\PbxConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VitalPbxDialer
{
    /** Start a VitalPBX click-to-call request. The mapped extension rings first. */
    public function dial(PbxConnection $connection, string $extension, string $destination): array
    {
        $baseUrl = rtrim((string) ($connection->base_url ?: config('vitalpbx.base_url')), '/');
        $apiKey = (string) ($connection->api_token ?: config('vitalpbx.api_key'));

        if ($baseUrl === '' || $apiKey === '') {
            throw new RuntimeException('Configure the VitalPBX API URL and key in the application .env or PBX profile.');
        }
        if (parse_url($baseUrl, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('VitalPBX API requests require HTTPS.');
        }

        $timeout = max(1, (int) config('vitalpbx.timeout', 10));
        $request = Http::acceptJson()->asMultipart()->withHeader('app-key', $apiKey)
            ->connectTimeout(min(5, $timeout))->timeout($timeout);
        if (filled(config('vitalpbx.tenant'))) {
            $request->withHeader('tenant', (string) config('vitalpbx.tenant'));
        }

        try {
            $response = $request->post($baseUrl.'/api/v2/core/click_to_call', [
                'caller' => $extension,
                'callee' => $destination,
                'cid_name' => $extension,
                'cid_number' => $extension,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            throw new RuntimeException('Could not reach the VitalPBX click-to-call API. Check the URL, TLS certificate, and firewall.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('VitalPBX click-to-call API returned HTTP '.$response->status().'. Check that the API key is allowed to originate calls for this tenant.');
        }
        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('VitalPBX returned an unexpected click-to-call response.');
        }
        $status = strtolower((string) ($payload['status'] ?? ''));
        if (($payload['success'] ?? null) === false || in_array($status, ['error', 'declined', 'failure', 'failed'], true)) {
            throw new RuntimeException('VitalPBX did not start the call. Check the extension registration, destination, tenant, and click-to-call API permission.');
        }

        return $payload;
    }
}
