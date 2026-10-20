<?php

namespace App\Jobs;

use App\Domain\Sms\TxtConnectClient;
use App\Models\SmsCampaign;
use App\Models\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendSmsMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Provider does not document an idempotency header; never blindly replay an ambiguous send.

    public function __construct(public int $messageId) {}

    public function handle(TxtConnectClient $client): void
    {
        $message = SmsMessage::find($this->messageId);
        if (! $message || $message->status !== 'queued') {
            Log::channel('sms')->notice('SMS queue job skipped.', ['message_id' => $this->messageId, 'found' => (bool) $message, 'status' => $message?->status]);
            return;
        }

        $message->update(['status' => 'sending', 'attempts' => $message->attempts + 1, 'failure_reason' => null]);
        Log::channel('sms')->info('SMS provider submission started.', ['message_id' => $message->id, 'organization_id' => $message->organization_id, 'phone' => $message->to, 'sender' => $message->sender, 'attempt' => $message->attempts, 'campaign_id' => $message->campaign_id]);
        try {
            $result = $client->send($message->to, $message->sender, $message->body);
            $message->update(['status' => 'submitted', 'provider_message_id' => $result['messageId'] ?? null, 'provider_response' => $result, 'submitted_at' => now()]);
            Log::channel('sms')->info('SMS accepted by provider.', ['message_id' => $message->id, 'organization_id' => $message->organization_id, 'phone' => $message->to, 'provider_message_id' => $result['messageId'] ?? null, 'attempt' => $message->attempts]);
        } catch (Throwable $e) {
            $message->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 1000)]);
            Log::channel('sms')->error('SMS provider submission failed.', ['message_id' => $message->id, 'organization_id' => $message->organization_id, 'phone' => $message->to, 'attempt' => $message->attempts, 'exception' => $e::class, 'reason' => $this->safeExceptionMessage($e)]);
            throw $e;
        } finally {
            if ($message->campaign_id) {
                $campaign = SmsCampaign::withCount(['messages as pending_count' => fn ($q) => $q->whereIn('status', ['queued', 'sending'])])->find($message->campaign_id);
                if ($campaign && $campaign->pending_count === 0) {
                    $campaign->update(['status' => 'completed', 'completed_at' => now()]);
                }
            }
        }
    }

    public function failed(Throwable $e): void
    {
        SmsMessage::whereKey($this->messageId)->where('status', 'sending')->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 1000)]);
        Log::channel('sms')->critical('SMS queue job permanently failed.', ['message_id' => $this->messageId, 'exception' => $e::class, 'reason' => $this->safeExceptionMessage($e)]);
    }

    private function safeExceptionMessage(Throwable $exception): string
    {
        $message = mb_substr($exception->getMessage(), 0, 500);
        $key = config('services.txtconnect.key');
        if (is_string($key) && $key !== '') {
            $message = str_replace($key, '[redacted]', $message);
        }

        return preg_replace('/(password|passwd|token|authorization)\s*[:=]\s*[^\s,;]+/i', '$1=[redacted]', $message) ?? '[unavailable]';
    }
}
