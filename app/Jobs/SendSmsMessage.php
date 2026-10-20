<?php

namespace App\Jobs;

use App\Domain\Sms\TxtConnectClient;
use App\Models\SmsCampaign;
use App\Models\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
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
        if (! $message || $message->status !== 'queued') return;

        $message->update(['status' => 'sending', 'attempts' => $message->attempts + 1, 'failure_reason' => null]);
        try {
            $result = $client->send($message->to, $message->sender, $message->body);
            $message->update(['status' => 'submitted', 'provider_message_id' => $result['messageId'] ?? null, 'provider_response' => $result, 'submitted_at' => now()]);
        } catch (Throwable $e) {
            $message->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 1000)]);
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
    }
}
