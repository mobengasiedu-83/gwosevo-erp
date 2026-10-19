<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeadFollowUpNotification extends Notification
{
    use Queueable;

    public function __construct(private Lead $lead, private ?LeadActivity $activity, private string $kind) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => $this->kind === 'escalation' ? 'lead.follow_up.escalated' : 'lead.follow_up.due',
            'title' => $this->kind === 'escalation' ? 'Overdue lead follow-up escalated' : 'Lead follow-up due',
            'lead_id' => $this->lead->id,
            'lead_reference' => $this->lead->reference,
            'lead_name' => $this->lead->full_name,
            'activity' => $this->activity?->subject ?? 'Next follow-up',
            'due_at' => ($this->activity?->due_at ?? $this->lead->next_follow_up_at)?->toIso8601String(),
            'url' => route('leads.show', $this->lead),
        ];
    }
}
