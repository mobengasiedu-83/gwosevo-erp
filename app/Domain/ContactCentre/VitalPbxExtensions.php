<?php

namespace App\Domain\ContactCentre;

use App\Models\PbxConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VitalPbxExtensions
{
    /** @return array<int, array{extension: string, name: string, technology: string, status: string}> */
    public function fetch(PbxConnection $connection): array
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
        $request = Http::acceptJson()
            ->withHeader('app-key', $apiKey)
            ->connectTimeout(min(5, $timeout))
            ->timeout($timeout);

        if (filled(config('vitalpbx.tenant'))) {
            $request->withHeader('tenant', (string) config('vitalpbx.tenant'));
        }

        try {
            $response = $request->get($baseUrl.'/api/v2/extensions');
        } catch (\Throwable $exception) {
            report($exception);
            throw new RuntimeException('Could not reach the VitalPBX extensions API. Check the URL, TLS certificate, and firewall.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('VitalPBX extensions API returned HTTP '.$response->status().'. Check the API key, tenant, and API permissions.');
        }

        $payload = $response->json();
        if (! is_array($payload) || (isset($payload['status']) && strtolower((string) $payload['status']) === 'error')) {
            throw new RuntimeException('VitalPBX returned an unexpected extensions response.');
        }

        $records = $this->findExtensionList($payload);
        if ($records === null) {
            throw new RuntimeException('VitalPBX response did not contain an extensions list.');
        }

        $extensions = [];
        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $extension = $this->firstScalar($record, ['extension', 'extension_number', 'number']);
            if ($extension === null || trim($extension) === '') {
                continue;
            }

            $extensions[] = [
                'extension' => mb_substr(trim($extension), 0, 32),
                'name' => mb_substr(trim($this->firstScalar($record, ['name', 'display_name', 'description']) ?? ''), 0, 120),
                'technology' => mb_substr(trim($this->firstScalar($record, ['technology', 'tech', 'driver', 'type']) ?? ''), 0, 40),
                'status' => mb_substr(trim($this->firstScalar($record, ['status', 'state', 'registration_status']) ?? ''), 0, 40),
            ];
        }

        return collect($extensions)->unique('extension')->sortBy('extension')->values()->all();
    }

    /** @return array<int, mixed>|null */
    private function findExtensionList(array $payload): ?array
    {
        if (array_is_list($payload)) {
            return $payload;
        }

        foreach (['data', 'extensions', 'results', 'items', 'records'] as $key) {
            if (! array_key_exists($key, $payload) || ! is_array($payload[$key])) {
                continue;
            }

            if (array_is_list($payload[$key])) {
                return $payload[$key];
            }

            $nested = $this->findExtensionList($payload[$key]);
            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    private function firstScalar(array $record, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($record[$key]) && is_scalar($record[$key])) {
                return (string) $record[$key];
            }
        }

        return null;
    }
}
