<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Email;

class LogEmailActivity
{
    public function sending(MessageSending $event): void
    {
        $this->write('sending', $event->message);
    }

    public function sent(MessageSent $event): void
    {
        $this->write('sent', $event->message);
    }

    public function failed(NotificationFailed $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        $recipient = null;
        try {
            if (method_exists($event->notifiable, 'routeNotificationFor')) {
                $recipient = $event->notifiable->routeNotificationFor('mail', $event->notification);
            }
        } catch (\Throwable) {
            // Failure logging must not interfere with notification error handling.
        }

        Log::channel('email')->error('Email notification failed.', [
            'notification' => $event->notification::class,
            'recipient' => $this->normalizeRecipients($recipient),
            'exception' => isset($event->data['exception']) ? $event->data['exception']::class : null,
        ]);
    }

    private function write(string $activity, Email $message): void
    {
        Log::channel('email')->info('Email '.$activity.'.', [
            'to' => $this->addresses($message->getTo()),
            'cc' => $this->addresses($message->getCc()),
            'bcc' => $this->addresses($message->getBcc()),
            'subject' => $message->getSubject(),
            'message_id' => $message->getHeaders()->get('Message-ID')?->getBodyAsString(),
        ]);
    }

    private function addresses(?array $addresses): array
    {
        return array_map(static fn ($address) => $address->getAddress(), $addresses ?? []);
    }

    private function normalizeRecipients(mixed $recipients): array
    {
        if (is_string($recipients)) {
            return [$recipients];
        }

        if (is_array($recipients)) {
            return array_values(array_filter($recipients, 'is_string'));
        }

        return [];
    }
}
