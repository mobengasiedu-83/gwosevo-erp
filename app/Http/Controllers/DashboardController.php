<?php

namespace App\Http\Controllers;

use App\Domain\Administration\Modules;
use App\Domain\Identity\LeadAccess;
use App\Domain\Sales\SalesAccess;
use App\Models\AccessReview;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\LeadActivity;
use App\Models\OpportunityActivity;
use App\Models\Opportunity;
use App\Notifications\ContractRenewalNotification;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();
        $showCRM = app(Modules::class)->enabled('crm') && $user->can('leads.view') && $user->active_organization_id;
        $showSales = app(Modules::class)->enabled('sales') && $user->can('opportunities.view') && $user->active_organization_id;
        $query = app(LeadAccess::class)->query($user)->whereNull('archived_at');
        $leadIds = fn () => (clone $query)->select('leads.id');
        $followUpActivities = $showCRM ? LeadActivity::with('lead')->where('type', 'task')->whereNull('completed_at')->whereNotNull('due_at')->whereHas('lead', fn ($q) => $q->whereNotIn('status', ['converted', 'disqualified'])->whereNull('archived_at')->whereIn('leads.id', $leadIds()))->orderBy('due_at')->take(5)->get() : collect();
        $followUpAlerts = $showCRM ? $user->unreadNotifications()->where('type', \App\Notifications\LeadFollowUpNotification::class)->latest()->take(5)->get() : collect();
        $salesAccess = app(SalesAccess::class);
        $salesIds = fn () => $salesAccess->query($user)->select('opportunities.id');
        $salesActivities = $showSales ? OpportunityActivity::with('opportunity')->where('type', 'task')->whereNull('completed_at')->whereNotNull('due_at')->where('due_at', '<=', now())->whereHas('opportunity', fn ($q) => $q->whereNotIn('stage', ['won', 'lost'])->whereIn('opportunities.id', $salesIds()))->orderBy('due_at')->take(5)->get() : collect();
        $salesAlerts = $showSales ? $user->unreadNotifications()->where('type', \App\Notifications\OpportunityActionNotification::class)->latest()->take(5)->get() : collect();
        $contractAlerts = app(Modules::class)->enabled('contracts') && $user->can('contracts.view') ? $user->unreadNotifications()->where('type', ContractRenewalNotification::class)->latest()->take(5)->get() : collect();
        $statusCounts = $showCRM ? (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') : collect();
        $reviews = AccessReview::whereNull('completed_at');
        if (! $user->isSuperAdmin()) {
            $reviews->where('reviewer_id', $user->id);
        }
        $activity = AuditEvent::where('organization_id', $user->active_organization_id);
        if (! $user->isSuperAdmin()) {
            $activity->where('actor_id', $user->id);
        }

        return view('dashboard', [
            'showCRM' => (bool) $showCRM,
            'contractAlerts' => $contractAlerts,
            'showSales' => (bool) $showSales, 'salesActivities' => $salesActivities, 'salesAlerts' => $salesAlerts,
            'leadCount' => $statusCounts->sum(), 'qualifiedCount' => $statusCounts->get('qualified', 0),
            'convertedCount' => $statusCounts->get('converted', 0), 'statusCounts' => $statusCounts,
            'recentLeads' => $showCRM ? (clone $query)->with('owner')->latest()->take(5)->get() : collect(),
            'followUpActivities' => $followUpActivities, 'followUpAlerts' => $followUpAlerts,
            'followUpCount' => $showCRM ? ((clone $query)->whereNotIn('status', ['converted', 'disqualified'])->where('next_follow_up_at', '<=', now())->count() + LeadActivity::where('type', 'task')->whereNull('completed_at')->where('due_at', '<=', now())->whereHas('lead', fn ($q) => $q->whereIn('leads.id', $leadIds()))->count()) : 0,
            'reviewCount' => $reviews->count(),
            'activeUserCount' => $user->isSuperAdmin() ? User::where('status', 'active')->count() : null,
            'recentActivity' => $activity->where('event', 'not like', 'identity.access.%')->latest('id')->take(4)->get(),
        ]);
    }
}
