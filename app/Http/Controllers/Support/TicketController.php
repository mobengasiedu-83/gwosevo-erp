<?php

namespace App\Http\Controllers\Support;

use App\Domain\Administration\Modules;
use App\Domain\Support\TicketWorkflow;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CallRecord;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketSlaPolicy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    private function enabled(): void { abort_unless(app(Modules::class)->enabled('support'), 404); }
    private function organization(Request $request): int
    {
        $id = (int) $request->user()->active_organization_id;
        abort_unless($id && $request->user()->canAccessOrganization($id), 403);
        return $id;
    }
    private function priorities(): array { return ['low', 'normal', 'high', 'critical']; }
    private function statuses(): array { return ['open', 'assigned', 'in_progress', 'waiting', 'resolved', 'closed']; }

    public function index(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('tickets.view'), 403); $org = $this->organization($request);
        $tickets = Ticket::where('organization_id', $org)->with(['category', 'assignee', 'team', 'contact', 'account'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s->where('reference', 'like', '%'.$request->search.'%')->orWhere('subject', 'like', '%'.$request->search.'%')->orWhere('requester_email', 'like', '%'.$request->search.'%')))
            ->latest('updated_at')->paginate(25)->withQueryString();
        $base = Ticket::where('organization_id', $org);
        $resolvedCount = (clone $base)->whereIn('status', ['resolved', 'closed'])->count();
        $compliantResolved = (clone $base)->whereIn('status', ['resolved', 'closed'])->whereNull('response_breached_at')->whereNull('resolution_breached_at')->count();
        $stats = [
            'open' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])->count(),
            'unassigned' => (clone $base)->whereNull('assigned_to_id')->whereNotIn('status', ['resolved', 'closed'])->count(),
            'sla_breached' => (clone $base)->where(fn ($q) => $q->whereNotNull('response_breached_at')->orWhereNotNull('resolution_breached_at'))->count(),
            'sla_compliance' => $resolvedCount ? (int) round($compliantResolved / $resolvedCount * 100) : 100,
            'reopened' => (clone $base)->where('reopen_count', '>', 0)->count(),
            'avg_resolution_hours' => round((float) (Ticket::where('organization_id', $org)->whereNotNull('resolved_at')->cursor()->avg(fn ($ticket) => $ticket->created_at->diffInMinutes($ticket->resolved_at)) ?? 0) / 60, 1),
        ];
        $categories = TicketCategory::where('organization_id', $org)->orderBy('name')->get();
        $teams = Team::where('organization_id', $org)->where('is_active', true)->orderBy('name')->get();
        $agents = User::where('status', 'active')->whereHas('organizations', fn ($q) => $q->whereKey($org))->orderBy('name')->get();
        $calls = CallRecord::where('organization_id', $org)->latest('started_at')->limit(50)->get();
        $slaPolicies = TicketSlaPolicy::where('organization_id', $org)->with('escalationOwner')->orderByRaw("FIELD(priority, 'critical','high','normal','low')")->get();
        return view('support.tickets.index', compact('tickets', 'stats', 'categories', 'teams', 'agents', 'calls', 'slaPolicies'));
    }

    public function create(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('tickets.create'), 403); $org = $this->organization($request);
        $categories = TicketCategory::where('organization_id', $org)->where('is_active', true)->orderBy('name')->get();
        $teams = Team::where('organization_id', $org)->where('is_active', true)->orderBy('name')->get();
        $agents = User::where('status', 'active')->whereHas('organizations', fn ($q) => $q->whereKey($org))->orderBy('name')->get();
        $contacts = Contact::where('organization_id', $org)->orderBy('first_name')->get();
        $accounts = Account::where('organization_id', $org)->orderBy('name')->get();
        $calls = CallRecord::where('organization_id', $org)->latest('started_at')->limit(50)->get();
        return view('support.tickets.create', compact('categories', 'teams', 'agents', 'contacts', 'accounts', 'calls'));
    }

    public function store(Request $request, TicketWorkflow $workflow)
    {
        $this->enabled(); abort_unless($request->user()->can('tickets.create'), 403); $org = $this->organization($request);
        $data = $this->validatedTicket($request, $org);
        $data['organization_id'] = $org;
        $data['channel'] = ! empty($data['call_record_id']) ? 'call' : ($data['channel'] ?? 'manual');
        if (! $request->user()->can('tickets.assign')) unset($data['team_id'], $data['assigned_to_id']);
        [$ticket, $token] = $workflow->create($data, $request->user(), $request->file('attachments', []));
        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket '.$ticket->reference.' created.')
            ->with('portal_link', $request->user()->can('tickets.portal') ? route('support.portal.show', $token) : null);
    }

    public function show(Request $request, Ticket $ticket)
    {
        $this->enabled(); abort_unless($request->user()->can('tickets.view'), 403); abort_unless((int) $ticket->organization_id === $this->organization($request), 404);
        $ticket->load(['category', 'assignee', 'team', 'contact', 'account', 'messages.attachments', 'messages.author', 'slaEvents']);
        if (! $request->user()->can('tickets.internal')) $ticket->setRelation('messages', $ticket->messages->where('visibility', 'public')->values());
        $agents = User::where('status', 'active')->whereHas('organizations', fn ($q) => $q->whereKey($ticket->organization_id))->orderBy('name')->get();
        $teams = Team::where('organization_id', $ticket->organization_id)->where('is_active', true)->orderBy('name')->get();
        return view('support.tickets.show', compact('ticket', 'agents', 'teams'));
    }

    public function reply(Request $request, Ticket $ticket, TicketWorkflow $workflow)
    {
        $this->enabled(); abort_unless($request->user()->can('tickets.reply'), 403); abort_unless((int) $ticket->organization_id === $this->organization($request), 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:20000'], 'visibility' => ['required', Rule::in(['public', 'internal'])], 'attachments' => ['nullable', 'array', 'max:5'], 'attachments.*' => ['file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,gif,txt,csv,doc,docx,xls,xlsx']]);
        abort_if($data['visibility'] === 'internal' && ! $request->user()->can('tickets.internal'), 403);
        $workflow->reply($ticket, $data, $request->user(), $request->file('attachments', []));
        return back()->with('success', 'Ticket reply added.');
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $this->enabled(); abort_unless($request->user()->can('tickets.assign'), 403); abort_unless((int) $ticket->organization_id === $this->organization($request), 404);
        $data = $request->validate(['team_id' => ['nullable', 'integer'], 'assigned_to_id' => ['nullable', 'integer']]);
        if (! empty($data['team_id'])) abort_unless(Team::where('organization_id', $ticket->organization_id)->whereKey($data['team_id'])->exists(), 422, 'Choose a team from this organization.');
        if (! empty($data['assigned_to_id'])) abort_unless(User::whereHas('organizations', fn ($q) => $q->whereKey($ticket->organization_id))->whereKey($data['assigned_to_id'])->exists(), 422, 'Choose an active organization member.');
        $ticket->fill(['team_id' => $data['team_id'] ?? null, 'assigned_to_id' => $data['assigned_to_id'] ?? null]);
        if ($ticket->assigned_to_id && $ticket->status === 'open') $ticket->status = 'assigned';
        $ticket->save();
        return back()->with('success', 'Ticket assignment saved.');
    }

    public function transition(Request $request, Ticket $ticket, TicketWorkflow $workflow)
    {
        $this->enabled(); abort_unless($request->user()->can('tickets.update'), 403); abort_unless((int) $ticket->organization_id === $this->organization($request), 404);
        $data = $request->validate(['status' => ['required', Rule::in($this->statuses())]]);
        $workflow->transition($ticket, $data['status']);
        return back()->with('success', 'Ticket status updated.');
    }

    public function saveCategory(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('tickets.manage'), 403); $org = $this->organization($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('ticket_categories')->where('organization_id', $org)]]);
        TicketCategory::create(['organization_id' => $org, 'name' => $data['name']]);
        return back()->with('success', 'Ticket category added.');
    }

    public function toggleCategory(Request $request, TicketCategory $category)
    {
        $this->enabled(); abort_unless($request->user()->can('tickets.manage'), 403);
        abort_unless((int) $category->organization_id === $this->organization($request), 404);
        $category->update(['is_active' => ! $category->is_active]);
        return back()->with('success', $category->is_active ? 'Ticket category enabled.' : 'Ticket category disabled.');
    }

    public function saveSla(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('tickets.manage'), 403); $org = $this->organization($request);
        $data = $request->validate(['priority' => ['required', Rule::in($this->priorities())], 'response_minutes' => ['required', 'integer', 'min:1', 'max:525600'], 'resolution_minutes' => ['required', 'integer', 'min:1', 'max:525600'], 'escalation_owner_id' => ['nullable', 'integer']]);
        if (! empty($data['escalation_owner_id'])) abort_unless(User::whereHas('organizations', fn ($q) => $q->whereKey($org))->whereKey($data['escalation_owner_id'])->exists(), 422, 'Choose an escalation owner from this organization.');
        TicketSlaPolicy::updateOrCreate(['organization_id' => $org, 'priority' => $data['priority']], $data);
        return back()->with('success', 'SLA policy saved. It applies to newly created tickets.');
    }

    public function attachment(Request $request, TicketAttachment $attachment)
    {
        $this->enabled(); abort_unless($request->user()->can('tickets.view'), 403);
        $attachment->load('message.ticket');
        abort_unless((int) $attachment->message->ticket->organization_id === $this->organization($request), 404);
        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function apiCreate(Request $request, TicketWorkflow $workflow)
    {
        $this->enabled(); abort_unless($request->user()->can('tickets.create'), 403); $org = $this->organization($request);
        $data = $this->validatedTicket($request, $org);
        $data['organization_id'] = $org;
        $data['channel'] = $data['channel'] ?? 'api';
        if (! $request->user()->can('tickets.assign')) unset($data['team_id'], $data['assigned_to_id']);
        [$ticket, $token] = $workflow->create($data, $request->user(), $request->file('attachments', []));
        $response = ['reference' => $ticket->reference, 'status' => $ticket->status];
        if ($request->user()->can('tickets.portal')) $response['portal_url'] = route('support.portal.show', $token);
        return response()->json($response, 201);
    }

    public function apiReply(Request $request, Ticket $ticket, TicketWorkflow $workflow)
    {
        $this->enabled(); abort_unless($request->user()->can('tickets.reply'), 403);
        abort_unless((int) $ticket->organization_id === $this->organization($request), 404);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:20000'], 'channel' => ['nullable', Rule::in(['email', 'api'])],
            'author_name' => ['nullable', 'string', 'max:150'], 'author_email' => ['nullable', 'email', 'max:255'],
            'attachments' => ['nullable', 'array', 'max:5'], 'attachments.*' => ['file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,gif,txt,csv,doc,docx,xls,xlsx'],
        ]);
        if (($data['channel'] ?? 'email') === 'api') {
            $workflow->reply($ticket, [...$data, 'visibility' => 'public'], $request->user(), $request->file('attachments', []), 'api');
        } else {
            $workflow->reply($ticket, [...$data, 'visibility' => 'public'], null, $request->file('attachments', []), 'email');
        }
        return response()->json(['accepted' => true, 'reference' => $ticket->reference]);
    }

    public function portalCreate(Request $request, Organization $organization, TicketWorkflow $workflow)
    {
        $this->enabled(); abort_unless($organization->is_active, 404);
        abort_if($request->filled('website'), 422);
        $data = $this->validatedTicket($request, (int) $organization->id);
        unset($data['team_id'], $data['assigned_to_id'], $data['contact_id'], $data['account_id'], $data['call_record_id']);
        $data['priority'] = 'normal';
        $data['organization_id'] = $organization->id;
        $data['channel'] = 'portal';
        [$ticket, $token] = $workflow->create($data, null, $request->file('attachments', []));
        return redirect()->route('support.portal.show', $token)->with('success', 'Your request was received. Keep this page link to track updates. Reference: '.$ticket->reference);
    }

    public function portalForm(Organization $organization)
    {
        $this->enabled(); abort_unless($organization->is_active, 404);
        $categories = TicketCategory::where('organization_id', $organization->id)->where('is_active', true)->orderBy('name')->get();
        return view('support.portal-create', compact('organization', 'categories'));
    }

    public function portalShow(string $token)
    {
        $this->enabled();
        $ticket = Ticket::with(['category', 'messages.attachments'])->where('portal_token_hash', hash('sha256', $token))->firstOrFail();
        return response()->view('support.portal', compact('ticket', 'token'))->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function portalReply(Request $request, string $token, TicketWorkflow $workflow)
    {
        $this->enabled();
        $ticket = Ticket::where('portal_token_hash', hash('sha256', $token))->firstOrFail();
        $data = $request->validate(['body' => ['required', 'string', 'max:20000'], 'attachments' => ['nullable', 'array', 'max:5'], 'attachments.*' => ['file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,gif,txt,csv,doc,docx,xls,xlsx']]);
        $workflow->reply($ticket, [...$data, 'visibility' => 'public'], null, $request->file('attachments', []), 'portal');
        return back()->with('success', 'Your reply was added.');
    }

    public function satisfaction(Request $request, string $token)
    {
        $this->enabled();
        $ticket = Ticket::where('portal_token_hash', hash('sha256', $token))->firstOrFail();
        abort_unless(in_array($ticket->status, ['resolved', 'closed'], true), 422);
        $data = $request->validate(['score' => ['required', 'integer', 'min:1', 'max:5'], 'comment' => ['nullable', 'string', 'max:3000']]);
        abort_if($ticket->satisfaction_received_at, 409, 'Thank you; feedback has already been recorded.');
        $ticket->update(['satisfaction_score' => $data['score'], 'satisfaction_comment' => $data['comment'] ?? null, 'satisfaction_received_at' => now()]);
        return back()->with('success', 'Thank you for your feedback.');
    }

    public function portalAttachment(string $token, TicketAttachment $attachment)
    {
        $this->enabled();
        $attachment->load('message.ticket');
        abort_unless(hash_equals((string) $attachment->message->ticket->portal_token_hash, hash('sha256', $token)) && $attachment->message->visibility === 'public', 404);
        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    private function validatedTicket(Request $request, int $organizationId): array
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'], 'initial_message' => ['required', 'string', 'max:20000'],
            'priority' => ['required', Rule::in($this->priorities())], 'category_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', 'integer'], 'account_id' => ['nullable', 'integer'], 'call_record_id' => ['nullable', 'integer'],
            'team_id' => ['nullable', 'integer'], 'assigned_to_id' => ['nullable', 'integer'],
            'channel' => ['nullable', Rule::in(['email', 'api', 'call', 'manual'])],
            'requester_name' => ['required', 'string', 'max:150'], 'requester_email' => ['nullable', 'email', 'max:255'], 'requester_phone' => ['nullable', 'string', 'max:40'],
            'attachments' => ['nullable', 'array', 'max:5'], 'attachments.*' => ['file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,gif,txt,csv,doc,docx,xls,xlsx'],
        ]);
        foreach (['category_id' => TicketCategory::class, 'contact_id' => Contact::class, 'account_id' => Account::class, 'call_record_id' => CallRecord::class, 'team_id' => Team::class] as $field => $model) {
            if (! empty($data[$field])) abort_unless($model::where('organization_id', $organizationId)->whereKey($data[$field])->exists(), 422, 'The selected '.$field.' is not in this organization.');
        }
        if (! empty($data['assigned_to_id'])) abort_unless(User::where('status', 'active')->whereHas('organizations', fn ($q) => $q->whereKey($organizationId))->whereKey($data['assigned_to_id'])->exists(), 422, 'Choose an active organization member.');
        return $data;
    }
}
