<?php

namespace App\Console\Commands;

use App\Domain\Administration\Modules;
use App\Domain\Sales\SalesAccess;
use App\Models\OpportunityActivity;
use App\Models\User;
use App\Notifications\OpportunityActionNotification;
use Illuminate\Console\Command;

class SendOpportunityActionAlerts extends Command
{
    protected $signature = 'sales:send-action-alerts';
    protected $description = 'Send in-app reminders for due sales opportunity tasks';

    public function handle(): int
    {
        if (! app(Modules::class)->enabled('sales')) return self::SUCCESS;
        OpportunityActivity::with(['opportunity.owner', 'creator'])->where('type', 'task')->whereNotNull('due_at')->where('due_at', '<=', now())->whereNull('completed_at')->whereNull('reminder_sent_at')
            ->whereHas('opportunity', fn ($q) => $q->whereNotIn('stage', ['won', 'lost']))->chunkById(100, function ($activities) {
                foreach ($activities as $activity) {
                    $opportunity = $activity->opportunity;
                    if (! $opportunity) continue;
                    $preferred = $opportunity->owner ?: $activity->creator;
                    $recipient = $preferred && $preferred->isActive() && $preferred->can('opportunities.view') && app(SalesAccess::class)->allows($preferred, $opportunity) ? $preferred : null;
                    if (! $recipient) $recipient = User::role('Administrator')->where('status', 'active')->get()->first(fn ($admin) => app(SalesAccess::class)->allows($admin, $opportunity));
                    if ($recipient) $recipient->notify(new OpportunityActionNotification($opportunity, $activity));
                    $activity->update(['reminder_sent_at' => now()]);
                }
            });
        return self::SUCCESS;
    }
}
