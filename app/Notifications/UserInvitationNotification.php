<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitationNotification extends Notification
{
    public function __construct(private string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Your GWO SEVO workspace invitation')->greeting('Hello '.$notifiable->name)
            ->line('You have been invited to the ERP workspace. Set your password to activate your account.')
            ->action('Accept invitation', $this->url)->line('This one-time invitation expires in 48 hours.');
    }
}
