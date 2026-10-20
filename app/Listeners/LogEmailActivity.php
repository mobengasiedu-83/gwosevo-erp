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

        $exception = $event->data['exception'] ?? null;

        Log::channel('email')->error('Email notification failed.', [
            'notification' => $event->notification::class,
            'recipient' => $this->normalizeRecipients($recipient),
            'exception' => $exception instanceof \Throwable ? $exception::class : null,
            'reason' => $this->safeExceptionMessage($exception),
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

    private function safeExceptionMessage(mixed $exception): ?string
    {
        if (! $exception instanceof \Throwable) {
            return null;
        }

        $message = mb_substr($exception->getMessage(), 0, 500);
        foreach ([config('mail.mailers.smtp.username'), config('mail.mailers.smtp.password')] as $secret) {
            if (is_string($secret) && $secret !== '') {
                $message = str_replace($secret, '[redacted]', $message);
            }
        }

        return preg_replace('/(password|passwd|token|authorization)\s*[:=]\s*[^\s,;]+/i', '$1=[redacted]', $message);
    }
}
