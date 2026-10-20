<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketRequesterUpdate extends Notification
{
    use Queueable;

    public function __construct(private Ticket $ticket, private string $update, private ?string $publicReply = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Ticket '.$this->ticket->reference.': '.$this->ticket->subject)
            ->greeting('Hello '.$this->ticket->requester_name)
            ->line($this->update);

        if ($this->publicReply !== null) {
            $message->line('Reply:')->line($this->publicReply);
        }

        if ($this->ticket->portal_token_encrypted) {
            $message->action('View and reply securely', route('support.portal.show', $this->ticket->portal_token_encrypted));
        }

        return $message->line('Ticket reference: '.$this->ticket->reference);
    }
}
