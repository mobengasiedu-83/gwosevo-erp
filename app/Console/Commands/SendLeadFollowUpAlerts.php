<?php

namespace App\Console\Commands;

use App\Domain\Identity\LeadAccess;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\LeadFollowUpNotification;
use Illuminate\Console\Command;

class SendLeadFollowUpAlerts extends Command
{
    protected $signature = 'crm:send-follow-up-alerts';
    protected $description = 'Notify lead owners about due follow-ups and escalate overdue tasks';

    public function handle(): int
    {
        $hours = max(1, (int) config('crm.follow_up_escalation_hours', 24));
        LeadActivity::with(['lead.owner', 'creator'])->where('type', 'task')->whereNotNull('due_at')->where('due_at', '<=', now())
            ->whereNull('completed_at')->whereHas('lead', fn ($q) => $q->whereNotIn('status', ['converted', 'disqualified'])->whereNull('archived_at'))
            ->whereNull('reminder_sent_at')->chunkById(100, function ($activities) {
                foreach ($activities as $activity) {
                    $lead = $activity->lead;
                    if (! $lead) continue;
                    $recipient = $this->authorizedRecipient($lead, $lead->owner ?: $activity->creator);
                    if ($recipient) $recipient->notify(new LeadFollowUpNotification($lead, $activity, 'reminder'));
                    $activity->update(['reminder_sent_at' => now()]);
                }
            });

        LeadActivity::with(['lead.owner', 'creator'])->where('type', 'task')->whereNotNull('due_at')->where('due_at', '<=', now()->subHours($hours))
            ->whereNotNull('reminder_sent_at')->whereNull('completed_at')
            ->whereHas('lead', fn ($q) => $q->whereNotIn('status', ['converted', 'disqualified'])->whereNull('archived_at'))
            ->whereNull('escalated_at')->chunkById(100, function ($activities) {
                foreach ($activities as $activity) {
                    $lead = $activity->lead;
                    if (! $lead) continue;
                    $ownerId = $lead->owner_id ?: $activity->created_by;
                    $managerId = OrganizationMembership::where('organization_id', $lead->organization_id)->where('user_id', $ownerId)->value('manager_id');
                    $manager = $managerId ? User::where('status', 'active')->find($managerId) : null;
                    $recipient = $this->authorizedRecipient($lead, $manager);
                    if ($recipient) $recipient->notify(new LeadFollowUpNotification($lead, $activity, 'escalation'));
                    $activity->update(['escalated_at' => now()]);
                }
            });

        Lead::with('owner')->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now())
            ->whereNull('follow_up_reminder_sent_at')->whereNull('archived_at')->whereNotIn('status', ['converted', 'disqualified'])
            ->chunkById(100, function ($leads) {
                foreach ($leads as $lead) {
                    $recipient = $this->authorizedRecipient($lead, $lead->owner ?: User::find($lead->created_by));
                    if ($recipient) $recipient->notify(new LeadFollowUpNotification($lead, null, 'reminder'));
                    $lead->update(['follow_up_reminder_sent_at' => now()]);
                }
            });

        Lead::with('owner')->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now()->subHours($hours))
            ->whereNotNull('follow_up_reminder_sent_at')->whereNull('follow_up_escalated_at')->whereNull('archived_at')
            ->whereNotIn('status', ['converted', 'disqualified'])->chunkById(100, function ($leads) {
                foreach ($leads as $lead) {
                    $ownerId = $lead->owner_id ?: $lead->created_by;
                    $managerId = OrganizationMembership::where('organization_id', $lead->organization_id)->where('user_id', $ownerId)->value('manager_id');
                    $manager = $managerId ? User::where('status', 'active')->find($managerId) : null;
                    $recipient = $this->authorizedRecipient($lead, $manager);
                    if ($recipient) $recipient->notify(new LeadFollowUpNotification($lead, null, 'escalation'));
                    $lead->update(['follow_up_escalated_at' => now()]);
                }
            });

        return self::SUCCESS;
    }

    private function authorizedRecipient(Lead $lead, ?User $preferred): ?User
    {
        if ($preferred && $preferred->isActive() && $preferred->can('leads.view') && app(LeadAccess::class)->allows($preferred, $lead)) return $preferred;

        return User::role('Administrator')->where('status', 'active')->get()
            ->first(fn (User $admin) => app(LeadAccess::class)->allows($admin, $lead));
    }
}
