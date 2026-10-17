<?php

namespace App\Http\Controllers\Identity;

use App\Domain\Identity\IdentityAudit;
use App\Http\Controllers\Controller;
use App\Models\ApprovalDelegation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DelegationController extends Controller
{
    public function index(Request $request)
    {
        $query = ApprovalDelegation::query();
        if (! $request->user()->hasRole('Administrator')) {
            $query->where(fn ($q) => $q->where('delegator_id', $request->user()->id)->orWhere('delegate_id', $request->user()->id));
        }

        return view('identity.delegations', ['delegations' => $query->latest()->paginate(25), 'users' => User::where('status', 'active')->where(function ($q) use ($request) {
            $q->whereHas('organizations', fn ($q) => $q->where('organizations.id', $request->user()->active_organization_id));
            $q->orWhereHas('roles', fn ($q) => $q->where('name', 'Administrator'));
        })->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['delegator_id' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 'active')], 'delegate_id' => ['required', 'integer', 'different:delegator_id', Rule::exists('users', 'id')->where('status', 'active')], 'permission' => ['required', Rule::in(['leads.approve'])], 'starts_at' => ['required', 'date', 'after_or_equal:today'], 'ends_at' => ['required', 'date', 'after:starts_at', 'before_or_equal:'.now()->addDays(90)->toDateTimeString()], 'reason' => ['required', 'string', 'max:1000']]);
        abort_unless($request->user()->hasRole('Administrator') || (int) $data['delegator_id'] === $request->user()->id, 403);
        $principal = User::findOrFail($data['delegator_id']);
        $delegate = User::findOrFail($data['delegate_id']);
        $organization = $request->user()->active_organization_id;
        if (! $organization || ! $principal->can($data['permission']) || ! $principal->canAccessOrganization($organization) || ! $delegate->canAccessOrganization($organization)) {
            throw ValidationException::withMessages(['delegate_id' => 'Both users must have organization access, and the delegator must hold the approval permission.']);
        }
        DB::transaction(function () use ($data, $organization, $request) {
            $delegation = ApprovalDelegation::create([...$data, 'organization_id' => $organization, 'created_by' => $request->user()->id]);
            IdentityAudit::change('delegation.created', $request->user()->id, null, $delegation->toArray());
        });

        return back()->with('success', 'Time-limited approval delegation created. It grants approval authority only within both users’ data scopes.');
    }

    public function revoke(Request $request, ApprovalDelegation $delegation)
    {
        abort_unless($request->user()->hasRole('Administrator') || $delegation->delegator_id === $request->user()->id, 403);
        DB::transaction(function () use ($delegation, $request) {
            $delegation->update(['revoked_at' => now()]);
            IdentityAudit::change('delegation.revoked', $request->user()->id, null, ['delegation_id' => $delegation->id]);
        });

        return back()->with('success', 'Delegation revoked.');
    }
}
