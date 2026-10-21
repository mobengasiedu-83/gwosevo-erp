<?php

namespace App\Http\Controllers;

use App\Domain\Administration\Modules;
use App\Http\Controllers\Controller;
use App\Models\CallEvaluation;
use App\Models\CallEvaluationForm;
use App\Models\CallRecord;
use App\Models\PbxExtension;
use App\Models\Ticket;
use App\Models\TicketSlaPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerExperienceController extends Controller
{
    private function organization(Request $request): int
    {
        $organizationId = (int) $request->user()->active_organization_id;
        abort_unless($organizationId && $request->user()->canAccessOrganization($organizationId), 403);
        return $organizationId;
    }

    private function enabled(): void
    {
        abort_unless(app(Modules::class)->enabled('contact_centre') || app(Modules::class)->enabled('support') || app(Modules::class)->enabled('sms'), 404);
    }

    public function liveMonitoring(Request $request)
    {
        $this->enabled();
        abort_unless($request->user()->can('calls.supervise'), 403);
        $organizationId = $this->organization($request);
        $extensions = PbxExtension::with(['user', 'connection'])->whereHas('connection', fn ($q) => $q->where('organization_id', $organizationId))->orderBy('queue')->orderBy('extension')->get();
        $calls = CallRecord::where('organization_id', $organizationId)->with(['agent', 'contact', 'account'])->latest('started_at')->limit(50)->get();
        $lastActivity = $extensions->pluck('activity_at')->filter()->max();
        return view('customer-experience.live-monitoring', compact('extensions', 'calls', 'lastActivity'));
    }

    public function callRecords(Request $request)
    {
        $this->enabled();
        abort_unless($request->user()->can('calls.view'), 403);
        $organizationId = $this->organization($request);
        $calls = CallRecord::where('organization_id', $organizationId)->with(['agent', 'contact', 'account'])
            ->when($request->filled('queue'), fn ($q) => $q->where('queue', $request->string('queue')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('direction'), fn ($q) => $q->where('direction', $request->string('direction')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($search) => $search->where('from_number', 'like', '%'.$request->search.'%')->orWhere('to_number', 'like', '%'.$request->search.'%')->orWhere('disposition', 'like', '%'.$request->search.'%')->orWhereHas('contact', fn ($contact) => $contact->where('first_name', 'like', '%'.$request->search.'%')->orWhere('last_name', 'like', '%'.$request->search.'%'))->orWhereHas('account', fn ($account) => $account->where('name', 'like', '%'.$request->search.'%'))))
            ->latest('started_at')->paginate(30)->withQueryString();
        $queues = CallRecord::where('organization_id', $organizationId)->whereNotNull('queue')->distinct()->orderBy('queue')->pluck('queue');
        return view('customer-experience.call-records', compact('calls', 'queues'));
    }

    public function recordings(Request $request)
    {
        $this->enabled();
        abort_unless($request->user()->can('calls.recordings.view'), 403);
        $organizationId = $this->organization($request);
        $calls = CallRecord::where('organization_id', $organizationId)->whereNotNull('recording_reference')->with(['agent', 'contact', 'account'])->latest('started_at')->paginate(25)->withQueryString();
        return view('customer-experience.recordings', compact('calls'));
    }

    public function qualityAssurance(Request $request)
    {
        $this->enabled();
        abort_unless($request->user()->can('calls.qa.manage'), 403);
        $organizationId = $this->organization($request);
        $evaluations = CallEvaluation::query()->whereHas('callRecord', fn ($q) => $q->where('organization_id', $organizationId))->with(['callRecord.agent', 'form', 'evaluator'])->latest()->paginate(25);
        $forms = CallEvaluationForm::where('organization_id', $organizationId)->orderBy('name')->get();
        return view('customer-experience.quality-assurance', compact('evaluations', 'forms'));
    }

    public function sla(Request $request)
    {
        $this->enabled();
        abort_unless($request->user()->can('tickets.view'), 403);
        $organizationId = $this->organization($request);
        $policies = TicketSlaPolicy::where('organization_id', $organizationId)->with('escalationOwner')->orderByRaw("FIELD(priority, 'critical','high','normal','low')")->get();
        $breached = Ticket::where('organization_id', $organizationId)->whereNotIn('status', ['resolved', 'closed'])->where(fn ($q) => $q->whereNotNull('response_breached_at')->orWhereNotNull('resolution_breached_at'))->with(['assignee', 'team'])->latest('updated_at')->paginate(25);
        $stats = ['open' => Ticket::where('organization_id', $organizationId)->whereNotIn('status', ['resolved', 'closed'])->count(), 'breached' => Ticket::where('organization_id', $organizationId)->where(fn ($q) => $q->whereNotNull('response_breached_at')->orWhereNotNull('resolution_breached_at'))->count()];
        $agents = \App\Models\User::where('status', 'active')->whereHas('organizations', fn ($q) => $q->whereKey($organizationId))->orderBy('name')->get();
        return view('customer-experience.sla', compact('policies', 'breached', 'stats', 'agents'));
    }

    public function feedback(Request $request)
    {
        $this->enabled();
        abort_unless($request->user()->can('tickets.view'), 403);
        $organizationId = $this->organization($request);
        $feedback = Ticket::where('organization_id', $organizationId)->whereNotNull('satisfaction_received_at')->latest('satisfaction_received_at')->paginate(25);
        $summary = ['responses' => Ticket::where('organization_id', $organizationId)->whereNotNull('satisfaction_score')->count(), 'average' => round((float) Ticket::where('organization_id', $organizationId)->whereNotNull('satisfaction_score')->avg('satisfaction_score'), 1), 'follow_up' => Ticket::where('organization_id', $organizationId)->whereNotNull('satisfaction_score')->where('satisfaction_score', '<=', 2)->count()];
        return view('customer-experience.feedback', compact('feedback', 'summary'));
    }

    public function reports(Request $request)
    {
        $this->enabled();
        $user = $request->user();
        abort_unless($user->can('calls.view') || $user->can('tickets.reports') || $user->can('sms.view'), 403);
        $organizationId = $this->organization($request);
        $stats = [];
        if ($user->can('calls.view') && app(Modules::class)->enabled('contact_centre')) {
            $stats['Calls'] = CallRecord::where('organization_id', $organizationId)->count();
            $stats['Abandoned calls'] = CallRecord::where('organization_id', $organizationId)->where('status', 'abandoned')->count();
            $stats['Call QA average'] = round((float) CallEvaluation::whereHas('callRecord', fn ($q) => $q->where('organization_id', $organizationId))->avg('score_percent'), 1).'%';
        }
        if ($user->can('tickets.reports') && app(Modules::class)->enabled('support')) {
            $stats['Tickets'] = Ticket::where('organization_id', $organizationId)->count();
            $stats['Open tickets'] = Ticket::where('organization_id', $organizationId)->whereNotIn('status', ['resolved', 'closed'])->count();
            $stats['SLA breaches'] = Ticket::where('organization_id', $organizationId)->where(fn ($q) => $q->whereNotNull('response_breached_at')->orWhereNotNull('resolution_breached_at'))->count();
            $stats['Customer feedback average'] = round((float) Ticket::where('organization_id', $organizationId)->whereNotNull('satisfaction_score')->avg('satisfaction_score'), 1).'/5';
        }
        if ($user->can('sms.view') && app(Modules::class)->enabled('sms')) {
            $stats['SMS sent'] = DB::table('sms_messages')->where('organization_id', $organizationId)->count();
            $stats['SMS delivery failures'] = DB::table('sms_messages')->where('organization_id', $organizationId)->where('status', 'failed')->count();
        }
        return view('customer-experience.reports', compact('stats'));
    }
}
