<?php

namespace Tests\Feature;

use App\Domain\CRM\LeadService;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['leads.view', 'leads.create', 'leads.update', 'leads.assign', 'leads.qualify', 'leads.convert'] as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }
        $role = Role::create(['name' => 'Administrator', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::all());
        $this->organization = Organization::create(['name' => 'GWO SEVO', 'code' => 'GWO']);
        $this->administrator = User::factory()->create(['status' => 'active', 'active_organization_id' => $this->organization->id]);
        $this->administrator->organizations()->attach($this->organization->id, ['scope' => 'organization']);
        $this->administrator->assignRole($role);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('leads.index'))->assertRedirect(route('login'));
    }

    public function test_user_cannot_view_a_lead_outside_their_organization(): void
    {
        $salesRole = Role::create(['name' => 'Sales', 'guard_name' => 'web']);
        $salesRole->syncPermissions(Permission::all());
        $salesUser = User::factory()->create(['status' => 'active', 'active_organization_id' => $this->organization->id]);
        $salesUser->organizations()->attach($this->organization->id, ['scope' => 'organization']);
        $salesUser->assignRole($salesRole);
        $other = Organization::create(['name' => 'Other', 'code' => 'OTHER']);
        $lead = Lead::create(['organization_id' => $other->id, 'reference' => 'LD-OTHER-1', 'first_name' => 'Outside', 'last_name' => 'Lead', 'source' => 'manual', 'status' => 'new']);
        $this->actingAsVerified($salesUser)->get(route('leads.show', $lead))->assertForbidden();
    }

    public function test_qualified_lead_converts_to_commercial_records_and_is_audited(): void
    {
        $service = app(LeadService::class);
        $lead = $service->create(['organization_id' => $this->organization->id, 'first_name' => 'Ama', 'last_name' => 'Mensah', 'email' => 'ama@example.test', 'company_name' => 'Acme Ghana', 'source' => 'manual'], $this->administrator);
        $service->qualify($lead, ['budget' => 25000, 'authority' => 'Director', 'need' => 'Customer support', 'timeline' => 'Q1', 'score' => 85], $this->administrator);
        $service->convert($lead->fresh(), $this->administrator);
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'converted']);
        $this->assertDatabaseHas('accounts', ['organization_id' => $this->organization->id, 'name' => 'Acme Ghana']);
        $this->assertDatabaseHas('contacts', ['email' => 'ama@example.test']);
        $this->assertDatabaseHas('opportunities', ['lead_id' => $lead->id]);
        $this->assertDatabaseHas('audit_events', ['event' => 'lead.converted', 'auditable_id' => $lead->id]);
    }
}
