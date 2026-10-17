<?php

namespace Tests\Feature;

use App\Domain\Organization\Assignments;
use App\Domain\Organization\Structure;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->org = Organization::firstOrFail();
        $this->admin = User::factory()->create(['status' => 'active', 'active_organization_id' => $this->org->id]);
        $this->admin->assignRole('Administrator');
        $this->actingAsVerified($this->admin);
    }

    private function member(?User $user = null): OrganizationMembership
    {
        $user ??= User::factory()->create(['status' => 'active', 'active_organization_id' => $this->org->id]);

        return OrganizationMembership::create(['user_id' => $user->id, 'organization_id' => $this->org->id, 'scope' => 'organization']);
    }

    private function unit(string $type, array $extra = [])
    {
        return app(Structure::class)->save($type, [...['code' => 'NEW-'.strtoupper(substr($type, 0, 4)), 'name' => 'New '.$type, 'title' => 'New position', 'is_active' => 1, 'currency' => 'GHS', 'kind' => 'both'], ...$extra], $this->org);
    }

    public function test_all_catalogs_create_edit_and_list_and_chart_render(): void
    {
        $this->get('/structure')->assertOk();
        foreach (['departments', 'branches', 'teams', 'positions', 'cost-centres'] as $type) {
            $base = '/structure/'.$this->org->id.'/catalog/'.$type;
            $this->get($base.'/create')->assertOk()->assertSee('Save');
            $item = $this->unit($type);
            $this->get($base)->assertOk()->assertSee($item->code);
            $this->get($base.'/'.$item->id.'/edit')->assertOk();
        }
        $this->member($this->admin);
        $this->get('/structure/'.$this->org->id)->assertOk()->assertSee('Organization chart');
        $membership = OrganizationMembership::where('user_id', $this->admin->id)->first();
        $this->get('/structure/'.$this->org->id.'/people/'.$membership->id.'/edit')->assertOk();
        foreach (['legal-entities', 'organizations'] as $type) {
            $this->get('/structure/catalog/'.$type)->assertOk();
            $this->get('/structure/catalog/'.$type.'/create')->assertOk();
        }
    }

    public function test_http_writes_are_audited_and_codes_are_immutable(): void
    {
        $base = '/structure/'.$this->org->id.'/catalog/departments';
        $this->post($base, ['code' => 'ENGINEERING', 'name' => 'Engineering', 'is_active' => 1])->assertRedirect();
        $department = Department::where('code', 'ENGINEERING')->firstOrFail();
        $this->assertDatabaseHas('audit_events', ['event' => 'organization.departments.saved', 'auditable_id' => $department->id]);
        $this->put($base.'/'.$department->id, ['code' => 'CHANGED', 'name' => 'Engineering', 'is_active' => 1])->assertSessionHasErrors('code');
        $this->assertSame('ENGINEERING', $department->fresh()->code);
    }

    public function test_staff_can_only_view_member_organizations_and_cannot_manage(): void
    {
        $membership = $this->member();
        $this->actingAsVerified($membership->user);
        $this->get('/structure')->assertOk()->assertSee($this->org->name);
        $this->get('/structure/'.$this->org->id)->assertOk();
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Other entity', 'is_active' => 1]);
        $this->get('/structure/'.$other->id)->assertForbidden();
        $this->get('/structure/'.$this->org->id.'/catalog/departments')->assertForbidden();
        $this->post('/structure/catalog/legal-entities', ['code' => 'BAD'])->assertForbidden();
    }

    public function test_organization_record_ids_cannot_escape_parent_route(): void
    {
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Other', 'is_active' => 1]);
        $department = Department::create(['organization_id' => $other->id, 'name' => 'Private', 'code' => 'PRIVATE', 'is_active' => 1]);
        $this->get('/structure/'.$this->org->id.'/catalog/departments/'.$department->id.'/edit')->assertNotFound();
    }

    public function test_parent_and_position_hierarchies_reject_cycles(): void
    {
        $one = $this->unit('departments');
        $two = $this->unit('departments', ['code' => 'CHILD', 'parent_id' => $one->id]);
        try {
            $this->unit('departments', ['parent_id' => $two->id]);
            $this->fail('Expected duplicate code rejection');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->errors());
        }
        $this->expectException(ValidationException::class);
        app(Structure::class)->save('departments', ['code' => $one->code, 'name' => $one->name, 'is_active' => 1, 'parent_id' => $two->id], $this->org, $one);
    }

    public function test_position_reporting_cycle_is_rejected(): void
    {
        $one = $this->unit('positions');
        $two = $this->unit('positions', ['code' => 'REPORT', 'reports_to_position_id' => $one->id]);
        $this->expectException(ValidationException::class);
        app(Structure::class)->save('positions', ['code' => $one->code, 'title' => 'Manager', 'is_active' => 1, 'reports_to_position_id' => $two->id], $this->org, $one);
    }

    public function test_legal_entities_and_operating_organization_constraints(): void
    {
        $legal = app(Structure::class)->save('legal-entities', ['code' => 'LEGAL', 'name' => 'Legal company', 'is_active' => 1, 'currency' => 'GHS', 'timezone' => 'Africa/Accra'], null);
        $parent = app(Structure::class)->save('organizations', ['code' => 'PARENT', 'name' => 'Parent', 'is_active' => 1, 'timezone' => 'Africa/Accra', 'legal_entity_id' => $legal->id], null);
        $this->expectException(ValidationException::class);
        app(Structure::class)->save('organizations', ['code' => 'CHILD', 'name' => 'Child', 'is_active' => 1, 'timezone' => 'Africa/Accra', 'parent_id' => $parent->id], null);
    }

    public function test_complete_assignment_and_cost_split_preserve_password(): void
    {
        $member = $this->member();
        $manager = $this->member();
        $hash = $member->user->password;
        $department = $this->unit('departments');
        $team = $this->unit('teams', ['department_id' => $department->id]);
        $branch = $this->unit('branches');
        $position = $this->unit('positions', ['department_id' => $department->id]);
        $centre = $this->unit('cost-centres');
        $second = $this->unit('cost-centres', ['code' => 'SECOND']);
        app(Assignments::class)->save($member, ['department_id' => $department->id, 'team_id' => $team->id, 'branch_id' => $branch->id, 'position_id' => $position->id, 'manager_id' => $manager->user_id, 'allocations' => [['cost_centre_id' => $centre->id, 'percentage' => '33.33'], ['cost_centre_id' => $second->id, 'percentage' => '66.67']]]);
        $this->assertSame($position->id, $member->fresh()->position_id);
        $this->assertCount(2, $member->fresh()->allocations);
        $this->assertSame($hash, $member->user->fresh()->password);
        $this->assertDatabaseHas('audit_events', ['event' => 'organization.assignment.saved', 'auditable_id' => $member->id]);
        $this->get('/structure/'.$this->org->id.'?kind=approval')->assertOk()->assertSee($position->title);
    }

    public function test_invalid_allocations_roll_back_assignment(): void
    {
        $member = $this->member();
        $centre = $this->unit('cost-centres');
        try {
            app(Assignments::class)->save($member, ['allocations' => [['cost_centre_id' => $centre->id, 'percentage' => '99.99']]]);
            $this->fail('Expected validation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('allocations', $e->errors());
        }
        $this->assertCount(0, $member->fresh()->allocations);
    }

    public function test_cross_organization_assignments_are_rejected(): void
    {
        $member = $this->member();
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Other', 'is_active' => 1]);
        $branch = Branch::create(['organization_id' => $other->id, 'code' => 'FOREIGN', 'name' => 'Foreign', 'is_active' => 1]);
        $this->expectException(ValidationException::class);
        app(Assignments::class)->save($member, ['branch_id' => $branch->id]);
    }

    public function test_reporting_and_effective_approval_cycles_are_rejected(): void
    {
        $a = $this->member();
        $b = $this->member();
        app(Assignments::class)->save($a, ['manager_id' => $b->user_id]);
        try {
            app(Assignments::class)->save($b, ['manager_id' => $a->user_id]);
            $this->fail('Expected validation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('manager_id', $e->errors());
        }
        $this->expectException(ValidationException::class);
        app(Assignments::class)->save($b, ['approval_manager_id' => $a->user_id]);
    }

    public function test_assigned_units_and_managers_cannot_be_orphaned(): void
    {
        $member = $this->member();
        $branch = $this->unit('branches');
        app(Assignments::class)->save($member, ['branch_id' => $branch->id]);
        try {
            app(Structure::class)->save('branches', ['code' => $branch->code, 'name' => $branch->name, 'is_active' => 0], $this->org, $branch);
            $this->fail('Expected validation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('is_active', $e->errors());
        }
        $manager = $this->member();
        app(Assignments::class)->save($member, ['manager_id' => $manager->user_id]);
        $this->expectException(ValidationException::class);
        app(Assignments::class)->guardMembershipChanges($manager->user, []);
    }

    public function test_assignment_department_must_match_position_and_access_scope(): void
    {
        $member = $this->member();
        $department = $this->unit('departments');
        $position = $this->unit('positions', ['department_id' => $department->id]);
        $this->expectException(ValidationException::class);
        app(Assignments::class)->save($member, ['position_id' => $position->id]);
    }

    public function test_active_dependencies_block_organization_deactivation(): void
    {
        $this->member();
        $this->expectException(ValidationException::class);
        app(Structure::class)->save('organizations',['code' => $this->org->code, 'name' => $this->org->name, 'timezone' => 'Africa/Accra', 'is_active' => 0],null,$this->org);
    }
}
