<?php

namespace App\Domain\Organization;

use App\Domain\Administration\AdminAudit;
use App\Domain\Identity\SessionRegistry;
use App\Models\Branch;
use App\Models\CostCentre;
use App\Models\Department;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Position;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Assignments
{
    public function save(OrganizationMembership $membership, array $input): void
    {
        $data = validator($input, [
            'department_id' => ['nullable', 'integer'], 'team_id' => ['nullable', 'integer'], 'branch_id' => ['nullable', 'integer'], 'position_id' => ['nullable', 'integer'],
            'manager_id' => ['nullable', 'integer'], 'approval_manager_id' => ['nullable', 'integer'],
            'allocations' => ['nullable', 'array', 'max:100'], 'allocations.*.cost_centre_id' => ['required', 'integer', 'distinct'],
            'allocations.*.percentage' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:100'],
        ])->validate();
        DB::transaction(function () use ($membership, $data) {
            $organization = Organization::whereKey($membership->organization_id)->lockForUpdate()->firstOrFail();
            if (! $organization->is_active) {
                $this->fail('department_id', 'Reactivate this organization before changing assignments.');
            }
            $member = OrganizationMembership::whereKey($membership->id)->lockForUpdate()->firstOrFail();
            foreach (['department_id' => Department::class, 'team_id' => Team::class, 'branch_id' => Branch::class, 'position_id' => Position::class] as $key => $class) {
                if (! empty($data[$key]) && ! $class::where('organization_id', $organization->id)->where('is_active', true)->find($data[$key])) {
                    $this->fail($key, 'Choose an active record in this organization.');
                }
                $data[$key] = $data[$key] ?? null;
            }
            foreach (['team_id' => Team::class, 'position_id' => Position::class] as $key => $class) {
                $record = ! empty($data[$key]) ? $class::find($data[$key]) : null;
                if ($record && $record->department_id && (int) $record->department_id !== (int) $data['department_id']) {
                    $this->fail($key, 'The selected record belongs to a different department.');
                }
            }
            if (($member->scope === 'team' && ! $data['team_id']) || ($member->scope === 'department' && ! $data['department_id'])) {
                $this->fail('department_id', 'The account’s access scope requires a department or team. Change its access scope in Users & access first.');
            }
            foreach (['manager_id', 'approval_manager_id'] as $key) {
                $data[$key] = $data[$key] ?? null;
                if ($data[$key] && ! OrganizationMembership::where('organization_id', $organization->id)->where('user_id', $data[$key])->whereHas('user', fn ($q) => $q->where('status', 'active'))->exists()) {
                    $this->fail($key, 'Choose an active member of this organization.');
                }
            }
            $rows = OrganizationMembership::where('organization_id', $organization->id)->get()->keyBy('user_id');
            $rows[$member->user_id]->fill($data);
            foreach (['manager_id', 'approval_manager_id'] as $kind) {
                foreach ($rows as $row) {
                    $seen = [];
                    $current = $row;
                    while ($current) {
                        if (isset($seen[$current->user_id])) {
                            $this->fail($kind, 'Reporting and approval lines must not contain a cycle.');
                        }
                        $seen[$current->user_id] = true;
                        $next = $kind === 'approval_manager_id' ? ($current->approval_manager_id ?: $current->manager_id) : $current->manager_id;
                        $current = $next ? ($rows[$next] ?? null) : null;
                    }
                }
            }
            $allocations = $data['allocations'] ?? [];
            $total = 0;
            foreach ($allocations as $allocation) {
                if (! CostCentre::where('organization_id', $organization->id)->where('is_active', true)->find($allocation['cost_centre_id'])) {
                    $this->fail('allocations', 'Choose active cost centres within this organization.');
                }
                $parts = explode('.', (string) $allocation['percentage']);
                $total += (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
            }
            if ($allocations && $total !== 10000) {
                $this->fail('allocations', 'Allocation percentages must total exactly 100%.');
            }
            $old = $member->load('allocations')->toArray();
            unset($data['allocations']);
            $accessChanged = (int) $member->department_id !== (int) $data['department_id'] || (int) $member->team_id !== (int) $data['team_id'];
            $member->fill($data)->save();
            $member->allocations()->delete();
            $member->allocations()->createMany($allocations);
            if ($accessChanged) {
                app(SessionRegistry::class)->revokeAll($member->user);
            }
            AdminAudit::record('organization.assignment.saved', OrganizationMembership::class, $member->id, $old, $member->fresh()->load('allocations')->toArray(), $organization->id);
        }, 3);
    }

    public function guardMembershipChanges(User $user, array $memberships): void
    {
        $incoming = collect($memberships)->keyBy('organization_id');
        foreach (OrganizationMembership::where('user_id', $user->id)->get() as $old) {
            $new = $incoming->get($old->organization_id);
            if (! $new) {
                $dependent = OrganizationMembership::where('organization_id', $old->organization_id)->where(fn ($q) => $q->where('manager_id', $user->id)->orWhere('approval_manager_id', $user->id))->exists();
                foreach ([Department::class, Branch::class, Team::class, CostCentre::class] as $class) {
                    $dependent = $dependent || $class::where('organization_id', $old->organization_id)->where('manager_id', $user->id)->exists();
                }
                if ($dependent || $old->allocations()->exists()) {
                    $this->fail('memberships', 'Reassign management responsibilities and remove cost allocations in Organization structure before removing membership.');
                }
            } elseif ($old->position_id && ($position = Position::find($old->position_id)) && $position->department_id && (int) $position->department_id !== (int) ($new['department_id'] ?? null)) {
                $this->fail('memberships', 'Update the position in Organization structure before changing this member’s department.');
            }
        }
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
