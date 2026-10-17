<?php

namespace App\Domain\Organization;

use App\Domain\Administration\AdminAudit;
use App\Models\Branch;
use App\Models\CostCentre;
use App\Models\Department;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Position;
use App\Models\Team;
use App\Models\User;
use App\Models\UserCostCentreAllocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Structure
{
    public const TYPES = [
        'legal-entities' => [LegalEntity::class, 'Legal entities', 'Legal entity'],
        'organizations' => [Organization::class, 'Organizations', 'Organization'],
        'departments' => [Department::class, 'Departments', 'Department'],
        'branches' => [Branch::class, 'Branches', 'Branch'],
        'teams' => [Team::class, 'Teams', 'Team'],
        'positions' => [Position::class, 'Positions', 'Position'],
        'cost-centres' => [CostCentre::class, 'Cost centres', 'Cost centre'],
    ];

    public function definition(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }

    public function scoped(string $type): bool
    {
        return ! in_array($type, ['legal-entities', 'organizations'], true);
    }

    public function fields(string $type): array
    {
        $base = ['code' => ['Code', 'text'], $type === 'positions' ? 'title' : 'name' => [$type === 'positions' ? 'Title' : 'Name', 'text']];

        return [...$base, ...match ($type) {
            'legal-entities' => ['registration_number' => ['Registration number', 'text'], 'tax_number' => ['Tax number', 'text'], 'email' => ['Contact email', 'email'], 'phone' => ['Phone', 'text'], 'address' => ['Registered address', 'textarea'], 'currency' => ['Currency code', 'text'], 'timezone' => ['Time zone', 'timezone']],
            'organizations' => ['legal_entity_id' => ['Legal entity', 'select'], 'parent_id' => ['Parent organization', 'select'], 'email' => ['Email', 'email'], 'phone' => ['Phone', 'text'], 'address' => ['Address', 'textarea'], 'timezone' => ['Time zone', 'timezone']],
            'departments' => ['parent_id' => ['Parent department', 'select'], 'manager_id' => ['Department manager', 'select'], 'description' => ['Purpose and responsibilities', 'textarea']],
            'branches' => ['manager_id' => ['Branch manager', 'select'], 'email' => ['Email', 'email'], 'phone' => ['Phone', 'text'], 'address' => ['Office address', 'textarea'], 'city' => ['City', 'text'], 'country_code' => ['Country code', 'text']],
            'teams' => ['department_id' => ['Department', 'select'], 'manager_id' => ['Team supervisor', 'select'], 'description' => ['Team responsibilities', 'textarea']],
            'positions' => ['department_id' => ['Department', 'select'], 'reports_to_position_id' => ['Reports to position', 'select'], 'grade' => ['Grade / level', 'text'], 'job_description' => ['Job description', 'textarea']],
            'cost-centres' => ['department_id' => ['Department', 'select'], 'parent_id' => ['Parent cost centre', 'select'], 'manager_id' => ['Budget owner', 'select'], 'kind' => ['Allocation type', 'kind'], 'currency' => ['Currency code', 'text'], 'budget' => ['Budget (optional)', 'number']],
        }, 'is_active' => ['Availability', 'boolean']];
    }

    public function choices(string $type, ?Organization $organization, ?Model $record = null): array
    {
        $choices = [];
        if ($organization) {
            $choices['manager_id'] = User::where('status', 'active')->whereHas('organizations', fn ($q) => $q->where('organizations.id', $organization->id))->orderBy('name')->pluck('name', 'id');
            $choices['department_id'] = Department::where('organization_id', $organization->id)->where('is_active', true)->orderBy('name')->pluck('name', 'id');
            $choices['reports_to_position_id'] = Position::where('organization_id', $organization->id)->where('is_active', true)->when($record?->exists, fn ($q) => $q->whereKeyNot($record->id))->orderBy('title')->pluck('title', 'id');
            if (in_array($type, ['departments', 'cost-centres'], true)) {
                $class = self::TYPES[$type][0];
                $choices['parent_id'] = $class::where('organization_id', $organization->id)->where('is_active', true)->when($record?->exists, fn ($q) => $q->whereKeyNot($record->id))->orderBy('name')->pluck('name', 'id');
            }
        } else {
            $choices['legal_entity_id'] = LegalEntity::where('is_active', true)->orderBy('name')->pluck('name', 'id');
            $choices['parent_id'] = Organization::where('is_active', true)->when($record?->exists, fn ($q) => $q->whereKeyNot($record->id))->orderBy('name')->pluck('name', 'id');
        }

        return $choices;
    }

    public function save(string $type, array $input, ?Organization $organization, ?Model $record = null): Model
    {
        [$class] = $this->definition($type);
        abort_unless($this->scoped($type) === (bool) $organization, 404);
        $record ??= new $class;
        $rules = ['code' => ['required', 'regex:/^[A-Z0-9_-]{1,40}$/', Rule::unique($record->getTable())->when($organization, fn ($rule) => $rule->where('organization_id', $organization->id))->ignore($record->id)], 'is_active' => ['required', 'boolean']];
        foreach ($this->fields($type) as $field => [$label,$kind]) {
            if (isset($rules[$field])) {
                continue;
            }
            $rules[$field] = match ($kind) {
                'select' => ['nullable', 'integer'], 'timezone' => ['required', 'timezone'],
                'email' => ['nullable', 'email', 'max:255'], 'number' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
                'textarea' => ['nullable', 'string', 'max:10000'],
                'kind' => ['required', Rule::in(['cost', 'revenue', 'both'])],
                default => [in_array($field, ['name', 'title', 'currency'], true) ? 'required' : 'nullable', 'string', 'max:'.match ($field) {
                    'currency' => 3,'phone','grade' => 50,'registration_number','tax_number','city' => 100,default => 150
                }],
            };
        }
        if (isset($rules['currency'])) {
            $rules['currency'][] = 'regex:/^[A-Z]{3}$/';
        }
        if (isset($rules['country_code'])) {
            $rules['country_code'] = ['nullable', 'regex:/^[A-Z]{2}$/'];
        }
        $data = validator($input, $rules)->validate();
        if ($record->exists && $data['code'] !== $record->code) {
            $this->fail('code', 'Codes are permanent. Edit the name or create a new record.');
        }
        if ($organization) {
            $data['organization_id'] = $organization->id;
        }

        return DB::transaction(function () use ($type, $data, $record, $organization) {
            // An organization lock serializes structure/assignment changes in one unit.
            if ($organization) {
                Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            } else {
                DB::table('organizations')->orderBy('id')->lockForUpdate()->get(['id']);
            }
            if ($record->exists) {
                $record = $record->newQuery()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            }
            $this->validateLinks($type, $data, $record, $organization);
            if (! $data['is_active']) {
                $this->canDeactivate($type, $record);
            }
            $old = $record->exists ? $record->toArray() : null;
            $record->fill($data)->save();
            AdminAudit::record('organization.'.$type.'.saved', get_class($record), $record->id, $old, $record->toArray(), $organization?->id ?? ($type === 'organizations' ? $record->id : null));

            return $record;
        }, 3);
    }

    private function validateLinks(string $type, array $data, Model $record, ?Organization $organization): void
    {
        foreach (['department_id' => Department::class, 'reports_to_position_id' => Position::class] as $field => $class) {
            if (! empty($data[$field])) {
                $linked = $class::where('organization_id', $organization->id)->where('is_active', true)->find($data[$field]);
                if (! $linked) {
                    $this->fail($field, 'Choose an active record in this organization.');
                }
            }
        }
        if (! empty($data['manager_id'])) {
            $manager = User::where('status', 'active')->whereHas('organizations', fn ($q) => $q->where('organizations.id', $organization->id))->find($data['manager_id']);
            if (! $manager) {
                $this->fail('manager_id', 'The manager must be an active member of this organization.');
            }
        }
        if (! empty($data['legal_entity_id']) && ! LegalEntity::where('is_active', true)->find($data['legal_entity_id'])) {
            $this->fail('legal_entity_id', 'Choose an active legal entity.');
        }
        if (! empty($data['parent_id'])) {
            $class = get_class($record);
            $parent = $class::where('is_active', true)->when($organization, fn ($q) => $q->where('organization_id', $organization->id))->find($data['parent_id']);
            if (! $parent) {
                $this->fail('parent_id', 'Choose an active parent in the same structure.');
            }
            $this->acyclic($class, $record->id, (int) $data['parent_id'], 'parent_id');
            if ($type === 'organizations' && (int) $parent->legal_entity_id !== (int) ($data['legal_entity_id'] ?? null)) {
                $this->fail('legal_entity_id', 'Parent and child organizations must belong to the same legal entity.');
            }
        }
        if (! empty($data['reports_to_position_id'])) {
            $this->acyclic(Position::class, $record->id, (int) $data['reports_to_position_id'], 'reports_to_position_id');
        }
        if ($record->exists && $type === 'organizations' && (int) $record->legal_entity_id !== (int) ($data['legal_entity_id'] ?? null) && Organization::where('parent_id', $record->id)->exists()) {
            $this->fail('legal_entity_id', 'Reassign child organizations before changing the legal entity.');
        }
        if ($record->exists && in_array($type, ['teams', 'positions', 'cost-centres'], true) && (int) $record->department_id !== (int) ($data['department_id'] ?? null)) {
            $column = match ($type) {
                'teams' => 'team_id','positions' => 'position_id',default => null
            };
            $assigned = $column ? OrganizationMembership::where($column, $record->id)->exists() : UserCostCentreAllocation::where('cost_centre_id', $record->id)->exists();
            if ($assigned) {
                $this->fail('department_id', 'Reassign the current members or allocations before changing the department.');
            }
        }
        if ($organization && ! $organization->is_active && $data['is_active']) {
            $this->fail('is_active', 'Reactivate the organization before activating its records.');
        }
    }

    public function acyclic(string $class, ?int $id, int $parent, string $column): void
    {
        $seen = [];
        while ($parent) {
            if ($parent === $id || isset($seen[$parent])) {
                $this->fail($column, 'This relationship would create a cycle.');
            }
            $seen[$parent] = true;
            $parent = (int) $class::whereKey($parent)->value($column);
        }
    }

    private function canDeactivate(string $type, Model $record): void
    {
        if (! $record->exists) {
            return;
        }
        $activeMembers = OrganizationMembership::whereHas('user', fn ($q) => $q->where('status', '!=', 'inactive'));
        $blocked = match ($type) {
            'legal-entities' => Organization::where('legal_entity_id', $record->id)->where('is_active', true)->exists(),
            'organizations' => Organization::where('parent_id', $record->id)->where('is_active', true)->exists() || $activeMembers->where('organization_id', $record->id)->exists() || User::where('active_organization_id', $record->id)->where('status', '!=', 'inactive')->exists() || collect([Department::class, Branch::class, Team::class, Position::class, CostCentre::class])->contains(fn ($class) => $class::where('organization_id', $record->id)->where('is_active', true)->exists()),
            'departments' => $activeMembers->where('department_id', $record->id)->exists() || Department::where('parent_id', $record->id)->where('is_active', true)->exists() || collect([Team::class, Position::class, CostCentre::class])->contains(fn ($class) => $class::where('department_id', $record->id)->where('is_active', true)->exists()),
            'branches' => $activeMembers->where('branch_id', $record->id)->exists(),
            'teams' => $activeMembers->where('team_id', $record->id)->exists(),
            'positions' => $activeMembers->where('position_id', $record->id)->exists() || Position::where('reports_to_position_id', $record->id)->where('is_active', true)->exists(),
            'cost-centres' => CostCentre::where('parent_id', $record->id)->where('is_active', true)->exists() || UserCostCentreAllocation::where('cost_centre_id', $record->id)->whereHas('membership.user', fn ($q) => $q->where('status', '!=', 'inactive'))->exists(),
        };
        if ($blocked) {
            $this->fail('is_active', 'Reassign active members and deactivate or reassign dependent records before deactivating this record.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
