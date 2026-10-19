<?php

namespace App\Notifications;

use App\Models\Opportunity;
use App\Models\OpportunityActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OpportunityActionNotification extends Notification
{
    use Queueable;

    public function __construct(private Opportunity $opportunity, private OpportunityActivity $activity) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toDatabase(object $notifiable): array
    {
        return ['event' => 'opportunity.action.due', 'title' => 'Opportunity next action due', 'opportunity_id' => $this->opportunity->id, 'reference' => $this->opportunity->reference, 'name' => $this->opportunity->name, 'activity' => $this->activity->subject, 'due_at' => $this->activity->due_at?->toIso8601String(), 'url' => route('opportunities.show', $this->opportunity)];
    }
}
