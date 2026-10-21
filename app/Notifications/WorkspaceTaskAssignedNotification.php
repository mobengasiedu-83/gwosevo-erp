<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceTaskAssignedNotification extends Notification
{
    use Queueable;

    /** @param array<int, string> $channels */
    public function __construct(
        private readonly string $title,
        private readonly ?string $description,
        private readonly ?string $dueAt,
        private readonly int $organizationId,
        private readonly array $channels,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'A task was assigned to you',
            'body' => $this->title,
            'url' => route('workspace.tasks.index'),
            'event_key' => 'workspace.task.assigned',
            'organization_id' => $this->organizationId,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('A task was assigned to you: '.$this->title)
            ->greeting('Hello '.$notifiable->name)
            ->line('You have been assigned a task in your workspace.')
            ->line('Task: '.$this->title);

        if (filled($this->description)) {
            $mail->line('Details: '.$this->description);
        }

        if (filled($this->dueAt)) {
            $mail->line('Due: '.$this->dueAt);
        }

        return $mail->action('View your tasks', route('workspace.tasks.index'));
    }
}
