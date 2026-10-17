<?php

namespace App\Http\Controllers;

use App\Domain\Administration\Modules;
use App\Domain\Identity\LeadAccess;
use App\Models\AccessReview;
use App\Models\AuditEvent;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();
        $showCRM = app(Modules::class)->enabled('crm') && $user->can('leads.view') && $user->active_organization_id;
        $query = app(LeadAccess::class)->query($user);
        $statusCounts = $showCRM ? (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') : collect();
        $reviews = AccessReview::whereNull('completed_at');
        if (! $user->hasRole('Administrator')) {
            $reviews->where('reviewer_id', $user->id);
        }
        $activity = AuditEvent::where('organization_id', $user->active_organization_id);
        if (! $user->hasRole('Administrator')) {
            $activity->where('actor_id', $user->id);
        }

        return view('dashboard', [
            'showCRM' => (bool) $showCRM,
            'leadCount' => $statusCounts->sum(), 'qualifiedCount' => $statusCounts->get('qualified', 0),
            'convertedCount' => $statusCounts->get('converted', 0), 'statusCounts' => $statusCounts,
            'recentLeads' => $showCRM ? (clone $query)->with('owner')->latest()->take(5)->get() : collect(),
            'followUpCount' => $showCRM ? (clone $query)->whereNotIn('status', ['converted', 'disqualified'])->where('next_follow_up_at', '<=', now())->count() : 0,
            'reviewCount' => $reviews->count(),
            'activeUserCount' => $user->hasRole('Administrator') ? User::where('status', 'active')->count() : null,
            'recentActivity' => $activity->where('event', 'not like', 'identity.access.%')->latest('id')->take(4)->get(),
        ]);
    }
}
