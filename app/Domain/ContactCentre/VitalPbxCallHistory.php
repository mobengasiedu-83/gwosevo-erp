<?php

namespace App\Domain\ContactCentre;

use App\Models\Account;
use App\Models\CallRecord;
use App\Models\Contact;
use App\Models\PbxConnection;
use App\Models\PbxExtension;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VitalPbxCallHistory
{
    /** @return array{imported:int,updated:int,skipped:int,from:Carbon,to:Carbon} */
    public function sync(PbxConnection $connection, ?int $lookbackDays = null): array
    {
        $baseUrl = rtrim((string) ($connection->base_url ?: config('vitalpbx.base_url')), '/');
        $apiKey = (string) ($connection->api_token ?: config('vitalpbx.api_key'));

        if ($baseUrl === '' || $apiKey === '') {
            throw new RuntimeException('Configure the VitalPBX API URL and key in the application .env or PBX profile.');
        }
        if (parse_url($baseUrl, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('VitalPBX API requests require HTTPS.');
        }

        $settings = $connection->settings ?? [];
        $end = now();
        $overlapHours = max(1, (int) config('vitalpbx.cdr_overlap_hours', 24));
        $initialDays = max(1, (int) config('vitalpbx.cdr_initial_lookback_days', 7));
        if ($lookbackDays !== null) {
            $start = $end->copy()->subDays(max(1, min(365, $lookbackDays)));
        } elseif (filled(data_get($settings, 'calls_synced_at'))) {
            $start = Carbon::parse(data_get($settings, 'calls_synced_at'))->subHours($overlapHours);
        } else {
            $start = $end->copy()->subDays($initialDays);
        }

        $timeout = max(1, (int) config('vitalpbx.timeout', 10));
        $request = Http::acceptJson()
            ->withHeader('app-key', $apiKey)
            ->connectTimeout(min(5, $timeout))
            ->timeout($timeout);
        if (filled(config('vitalpbx.tenant'))) {
            $request->withHeader('tenant', (string) config('vitalpbx.tenant'));
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $offset = 0;
        $pageSize = max(1, min(500, (int) config('vitalpbx.cdr_page_size', 250)));
        $extensionNumbers = $connection->extensions()->pluck('extension')->all();

        try {
            for ($page = 0; $page < 100; $page++) {
                $response = $request->get($baseUrl.'/api/v2/cdr', [
                    'start_date' => $start->timestamp,
                    'end_date' => $end->timestamp,
                    'limit' => $offset.','.$pageSize,
                    'order_by' => 'calldate',
                    'order_type' => 'desc',
                ]);

                if (! $response->successful()) {
                    throw new RuntimeException('VitalPBX call history API returned HTTP '.$response->status().'. Check the API key, tenant, and CDR API permissions.');
                }

                $payload = $response->json();
                if (is_array($payload) && strtolower((string) ($payload['status'] ?? '')) === 'error') {
                    throw new RuntimeException('VitalPBX reported an error while reading call history. Check API access and CDR permissions.');
                }
                $records = is_array($payload) ? $this->findCallList($payload) : null;
                if ($records === null) {
                    throw new RuntimeException('VitalPBX returned an unexpected call history response.');
                }

                foreach ($records as $row) {
                    if (! is_array($row)) {
                        $skipped++;
                        continue;
                    }

                    $externalId = $this->externalId($row);
                    if ($externalId === null) {
                        $skipped++;
                        continue;
                    }

                    $source = $this->firstScalar($row, ['src', 'source', 'from', 'from_number', 'callerid']);
                    $destination = $this->firstScalar($row, ['dst', 'destination', 'to', 'to_number', 'called_number']);
                    $sourceExtension = $this->extensionIn($source, $extensionNumbers);
                    $destinationExtension = $this->extensionIn($destination, $extensionNumbers);
                    $extension = $this->firstScalar($row, ['queueAgent', 'queue_agent', 'agent_extension', 'extension', 'agent']);
                    $extension = $this->extensionIn($extension, $extensionNumbers) ?? $sourceExtension ?? $destinationExtension;
                    $direction = $this->direction($row, $sourceExtension, $destinationExtension);
                    $status = $this->status($row);
                    $startedAt = $this->date($this->firstScalar($row, ['calldate', 'start', 'started_at', 'timestamp', 'date']));
                    if ($startedAt === null) {
                        $skipped++;
                        continue;
                    }

                    $totalSeconds = max(0, (int) ($this->firstScalar($row, ['duration', 'duration_seconds', 'duration_sec']) ?? 0));
                    $talkSeconds = max(0, (int) ($this->firstScalar($row, ['billsec', 'talktime', 'talk_time', 'bill_seconds']) ?? 0));
                    $waitSeconds = max(0, (int) ($this->firstScalar($row, ['queueWaitTime', 'queue_wait_time', 'wait_seconds']) ?? ($totalSeconds - $talkSeconds)));
                    $answeredAt = $status === 'answered' ? $startedAt->copy()->addSeconds($waitSeconds) : null;
                    $endedAt = $startedAt->copy()->addSeconds($totalSeconds);
                    $queue = $this->firstScalar($row, ['queue', 'queue_name', 'queueId', 'queue_id']);
                    $mapping = $extension !== null
                        ? PbxExtension::where('connection_id', $connection->id)->where('extension', $extension)->first()
                        : null;

                    $number = $direction === 'inbound' ? $source : $destination;
                    $digits = preg_replace('/\D+/', '', $number ?? '');
                    $contact = $digits !== ''
                        ? Contact::where('organization_id', $connection->organization_id)->get()->first(fn ($candidate) => preg_replace('/\D+/', '', $candidate->phone ?? '') === $digits)
                        : null;
                    $account = $contact
                        ? Account::where('organization_id', $connection->organization_id)->find($contact->account_id)
                        : ($digits !== '' ? Account::where('organization_id', $connection->organization_id)->get()->first(fn ($candidate) => preg_replace('/\D+/', '', $candidate->phone ?? '') === $digits) : null);

                    $record = CallRecord::firstOrNew(['connection_id' => $connection->id, 'external_id' => $externalId]);
                    $isNew = ! $record->exists;
                    $record->fill([
                        'organization_id' => $connection->organization_id,
                        'direction' => $direction,
                        'status' => $status,
                        'from_number' => $source,
                        'to_number' => $destination,
                        'queue' => $queue,
                        'agent_id' => $mapping?->user_id,
                        'contact_id' => $contact?->id,
                        'account_id' => $account?->id,
                        'started_at' => $startedAt,
                        'answered_at' => $answeredAt,
                        'ended_at' => $endedAt,
                        'wait_seconds' => min(86400, $waitSeconds),
                        'duration_seconds' => min(86400, $talkSeconds > 0 ? $talkSeconds : $totalSeconds),
                    ]);
                    $record->save();
                    $isNew ? $imported++ : $updated++;
                }

                if (count($records) < $pageSize) {
                    break;
                }
                $offset += count($records);
            }
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            throw new RuntimeException('Could not reach the VitalPBX call history API. Check its URL, TLS certificate, firewall, and Laravel log.');
        }

        $settings['calls_synced_at'] = $end->toIso8601String();
        $connection->update(['settings' => $settings, 'last_connected_at' => $end]);

        return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped, 'from' => $start, 'to' => $end];
    }

    private function findCallList(array $payload): ?array
    {
        if (array_is_list($payload)) {
            return $payload;
        }
        foreach (['data', 'cdr', 'calls', 'results', 'items', 'records'] as $key) {
            if (! isset($payload[$key]) || ! is_array($payload[$key])) {
                continue;
            }
            if (array_is_list($payload[$key])) {
                return $payload[$key];
            }
            $nested = $this->findCallList($payload[$key]);
            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    private function externalId(array $row): ?string
    {
        $id = $this->firstScalar($row, ['uniqueid', 'unique_id', 'call_id', 'callid', 'id']);
        if ($id !== null && trim($id) !== '') {
            return mb_substr(trim($id), 0, 150);
        }

        $stable = array_intersect_key($row, array_flip(['linkedid', 'calldate', 'src', 'dst', 'duration', 'billsec', 'disposition', 'channel', 'dstchannel']));
        if (count($stable) < 3) {
            return null;
        }

        return 'cdr-'.hash('sha256', json_encode($stable, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function firstScalar(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && is_scalar($row[$key])) {
                return (string) $row[$key];
            }
        }

        return null;
    }

    private function date(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        if (is_numeric($value)) {
            $timestamp = (int) $value;
            if ($timestamp > 9999999999) {
                $timestamp = (int) floor($timestamp / 1000);
            }

            return Carbon::createFromTimestamp($timestamp);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function extensionIn(?string $value, array $extensions): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        foreach ($extensions as $extension) {
            $extension = (string) $extension;
            if ($value === $extension || preg_match('/(?:^|[^0-9])'.preg_quote($extension, '/').'(?:$|[^0-9])/', $value)) {
                return $extension;
            }
        }

        return null;
    }

    private function direction(array $row, ?string $sourceExtension, ?string $destinationExtension): string
    {
        if ($sourceExtension !== null && $destinationExtension === null) {
            return 'outbound';
        }
        if ($destinationExtension !== null && $sourceExtension === null) {
            return 'inbound';
        }

        $type = strtolower((string) ($this->firstScalar($row, ['calltype', 'call_type', 'direction', 'type']) ?? ''));

        return in_array($type, ['2', 'inbound', 'incoming', 'in'], true) ? 'inbound' : 'outbound';
    }

    private function status(array $row): string
    {
        $disposition = strtolower((string) ($this->firstScalar($row, ['disposition', 'status', 'hangupcause']) ?? ''));
        $queueCause = strtolower((string) ($this->firstScalar($row, ['queueCause', 'queue_cause']) ?? ''));
        if (str_contains($disposition, 'answer') || (int) ($this->firstScalar($row, ['billsec', 'talktime', 'talk_time']) ?? 0) > 0) {
            return 'answered';
        }
        if (str_contains($disposition, 'abandon') || str_contains($disposition, 'break_out') || str_contains($disposition, 'caller') || str_contains($queueCause, 'abandon') || str_contains($queueCause, 'break_out') || str_contains($queueCause, 'caller')) {
            return 'abandoned';
        }

        return 'missed';
    }
}
