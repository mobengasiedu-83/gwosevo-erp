<?php

namespace Tests\Feature;

use App\Domain\Administration\NumberSequence;
use App\Domain\Administration\Settings;
use App\Domain\Administration\TabularFile;
use App\Jobs\HealthHeartbeat;
use App\Livewire\Admin\HealthPanel;
use App\Models\CustomField;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\ReferenceValue;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $organization = Organization::where('code', 'GWO-SEVO')->firstOrFail();
        $this->admin = User::factory()->create(['active_organization_id' => $organization->id, 'status' => 'active']);
        $this->admin->assignRole('Administrator');
    }

    public function test_administration_denies_guests_staff_and_inactive_administrators(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $staff = User::factory()->create(['status' => 'active']);
        $this->actingAsVerified($staff)->get('/admin')->assertForbidden();
        $this->actingAsVerified($staff)->put('/admin/settings', ['section' => 'preferences'])->assertForbidden();
        $this->admin->update(['status' => 'inactive']);
        $this->actingAsVerified($this->admin)->get('/admin')->assertRedirect('/login');
    }

    public function test_every_configuration_screen_renders_for_administrator(): void
    {
        $this->actingAsVerified($this->admin);
        foreach (['general', 'branding', 'preferences', 'modules', 'fields', 'references', 'sequences', 'health', 'maintenance', 'audit'] as $section) {
            $this->get('/admin?section='.$section)->assertOk()->assertSee('System configuration');
        }
        $this->get('/admin/transfer')->assertOk();
    }

    public function test_admin_assets_and_livewire_requests_preserve_the_subdirectory(): void
    {
        $this->actingAsVerified($this->admin);
        $request = Request::create('https://localhost/gwosevo-erp/public/admin?section=health', 'GET', [], [], [], [
            'SCRIPT_NAME' => '/gwosevo-erp/public/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
            'PHP_SELF' => '/gwosevo-erp/public/index.php',
        ]);
        $response = TestResponse::fromBaseResponse(
            $this->app->make(Kernel::class)->handle($request)
        );
        $response->assertOk()
            ->assertSee('https://localhost/gwosevo-erp/public/css/administration.css', false)
            ->assertSee('https://localhost/gwosevo-erp/public/livewire/livewire.js', false)
            ->assertSee('data-update-uri="https://localhost/gwosevo-erp/public/livewire/update"', false);
    }

    public function test_preferences_are_validated_persisted_and_audited(): void
    {
        $this->actingAsVerified($this->admin)->put('/admin/settings', ['section' => 'preferences', 'currency' => 'GHS', 'pagination' => 25, 'financial_year_month' => 4])->assertRedirect();
        $this->assertSame(25, app(Settings::class)->get('pagination'));
        $this->assertDatabaseHas('audit_events', ['actor_id' => $this->admin->id, 'event' => 'admin.settings.updated']);
        $this->put('/admin/settings', ['section' => 'preferences', 'currency' => 'invalid', 'pagination' => 999, 'financial_year_month' => 13])->assertSessionHasErrors(['currency', 'pagination', 'financial_year_month']);
        $this->assertSame(25, app(Settings::class)->get('pagination'));
    }

    public function test_branding_is_applied_to_login_and_logo_can_be_removed(): void
    {
        Storage::fake('local');
        $this->actingAsVerified($this->admin)->put('/admin/settings', [
            'section' => 'branding', 'login_message' => 'Welcome to our workspace', 'brand_color' => '#123456',
            'document_footer' => 'GWO SEVO', 'logo' => UploadedFile::fake()->image('logo.png', 100, 100),
        ])->assertRedirect();
        $path = app(Settings::class)->get('logo_path');
        Storage::disk('local')->assertExists($path);
        $this->get('/branding/logo')->assertOk();
        auth()->logout();
        $this->get('/login')->assertOk()->assertSee('Welcome to our workspace')->assertSee('#123456');
        $this->actingAsVerified($this->admin)->put('/admin/settings', [
            'section' => 'branding', 'login_message' => 'Welcome', 'brand_color' => '#123456', 'remove_logo' => 1,
        ])->assertRedirect();
        Storage::disk('local')->assertMissing($path);
        $this->get('/branding/logo')->assertNotFound();
    }

    public function test_maintenance_blocks_staff_and_guests_but_retains_admin_recovery(): void
    {
        $this->actingAsVerified($this->admin)->put('/admin/settings', [
            'section' => 'maintenance', 'maintenance_enabled' => 1,
            'maintenance_message' => 'Scheduled service work', 'operational_notice' => 'Service window',
        ])->assertRedirect();
        $this->get('/admin')->assertOk();
        $staff = User::factory()->create(['status' => 'active']);
        $this->actingAsVerified($staff)->get('/dashboard')->assertStatus(503)->assertSee('Scheduled service work');
        auth()->logout();
        $this->get('/')->assertStatus(503);
        $this->get('/login')->assertOk();
        $this->actingAsVerified($this->admin)->put('/admin/settings', [
            'section' => 'maintenance', 'maintenance_enabled' => 0, 'maintenance_message' => 'Scheduled service work',
        ])->assertRedirect();
        $this->actingAsVerified($staff)->get('/dashboard')->assertOk();
    }

    public function test_disabling_crm_blocks_direct_access_and_core_modules_cannot_be_disabled(): void
    {
        $this->actingAsVerified($this->admin)->put('/admin/modules/crm', ['enabled' => 0])->assertRedirect();
        $this->get('/leads')->assertNotFound();
        $this->post('/leads', ['first_name' => 'Ama'])->assertNotFound();
        $this->put('/admin/modules/administration', ['enabled' => 0])->assertStatus(422);
        $this->put('/admin/modules/finance', ['enabled' => 1])->assertStatus(422);
        $this->put('/admin/modules/crm', ['enabled' => 1])->assertRedirect();
        $this->get('/leads')->assertOk();
    }

    public function test_numbering_preserves_existing_references_and_rejects_backwards_changes(): void
    {
        $service = app(NumberSequence::class);
        $this->assertSame('LD-00001', $service->next('lead'));
        $this->assertSame('LD-00002', $service->next('lead'));
        $this->actingAsVerified($this->admin)->put('/admin/sequences/lead', ['prefix' => 'LEAD-', 'padding' => 6, 'next_number' => 1])->assertSessionHasErrors('next_number');
        $this->put('/admin/sequences/lead', ['prefix' => 'LEAD-', 'padding' => 6, 'next_number' => 10])->assertRedirect();
        $this->assertSame('LEAD-000010', $service->next('lead'));
    }

    public function test_custom_fields_validate_lead_capture_and_keep_disabled_values_on_edit(): void
    {
        $this->actingAsVerified($this->admin)->post('/admin/fields', [
            'key' => 'service_interest', 'label' => 'Service interest', 'type' => 'select',
            'options_text' => "Support\nConsulting", 'is_required' => 1, 'is_active' => 1,
        ])->assertRedirect();
        $payload = ['first_name' => 'Ama', 'last_name' => 'Test', 'source' => 'manual'];
        $this->post('/leads', $payload)->assertSessionHasErrors('service_interest');
        $this->assertDatabaseCount('leads', 0);
        $this->post('/leads', [...$payload, 'custom_values' => ['service_interest' => 'Invalid']])->assertSessionHasErrors('service_interest');
        $this->post('/leads', [...$payload, 'custom_values' => ['service_interest' => 'Support']])->assertRedirect();
        $lead = Lead::firstOrFail();
        $this->assertSame('Support', $lead->custom_values['service_interest']);
        $this->get('/leads/'.$lead->id)->assertSee('Service interest')->assertSee('Support');
        $field = CustomField::firstOrFail();
        $this->put('/admin/fields/'.$field->id, ['label' => 'Service interest', 'is_required' => 0, 'is_active' => 0, 'options_text' => "Support\nConsulting"])->assertRedirect();
        $this->put('/leads/'.$lead->id, $payload)->assertRedirect();
        $this->assertSame('Support', $lead->fresh()->custom_values['service_interest']);
    }

    public function test_reference_import_requires_preview_and_commits_validated_mapped_rows(): void
    {
        $file = UploadedFile::fake()->createWithContent('reference.csv', "Kind,Key,Name,Parent\ncountry,GH,Ghana,\nregion,GA,Greater Accra,GH\n");
        $this->actingAsVerified($this->admin)->post('/admin/transfer/upload', ['file' => $file])->assertRedirect();
        $this->post('/admin/transfer/commit')->assertStatus(422);
        $this->post('/admin/transfer/preview', ['mapping' => ['category' => 0, 'code' => 1, 'label' => 2, 'parent_code' => 3]])->assertRedirect();
        $this->assertDatabaseCount('reference_values', 0);
        $this->post('/admin/transfer/commit')->assertRedirect();
        $this->assertDatabaseHas('reference_values', ['category' => 'region', 'code' => 'GA', 'parent_code' => 'GH']);
        $this->assertDatabaseCount('reference_values', 2);
        $this->assertDatabaseHas('audit_events', ['event' => 'admin.reference.imported']);
        $this->post('/admin/transfer/commit')->assertStatus(422);
    }

    public function test_invalid_import_does_not_partially_create_reference_values(): void
    {
        $file = UploadedFile::fake()->createWithContent('reference.csv', "category,code,label\ncountry,GH,Ghana\ncountry,bad code,Invalid\n");
        $this->actingAsVerified($this->admin)->post('/admin/transfer/upload', ['file' => $file])->assertRedirect();
        $this->post('/admin/transfer/preview', ['mapping' => ['category' => 0, 'code' => 1, 'label' => 2]])->assertSessionHasErrors('mapping');
        $this->post('/admin/transfer/commit')->assertStatus(422);
        $this->assertDatabaseCount('reference_values', 0);
    }

    public function test_reference_validation_rejects_orphan_regions_and_duplicate_codes(): void
    {
        $this->actingAsVerified($this->admin)->post('/admin/references', ['category' => 'region', 'code' => 'GA', 'label' => 'Greater Accra', 'parent_code' => 'GH', 'is_active' => 1])->assertSessionHasErrors('parent_code');
        $country = ['category' => 'country', 'code' => 'GH', 'label' => 'Ghana', 'is_active' => 1];
        $this->post('/admin/references', $country)->assertRedirect();
        $this->post('/admin/references', $country)->assertSessionHasErrors('code');
    }

    public function test_excel_export_round_trips_and_csv_neutralizes_formulas(): void
    {
        ReferenceValue::create(['category' => 'industry', 'code' => 'TECH', 'label' => '=1+1', 'is_active' => true]);
        $this->actingAsVerified($this->admin);
        $xlsx = $this->post('/admin/transfer/export', ['format' => 'xlsx'])->assertOk();
        $file = UploadedFile::fake()->createWithContent('export.xlsx', $xlsx->getContent());
        $read = app(TabularFile::class)->read($file);
        $this->assertSame('=1+1', $read['rows'][0][2]);
        $this->assertSame('category', $read['header'][0]);
        $csv = $this->post('/admin/transfer/export', ['format' => 'csv'])->assertOk();
        $this->assertStringContainsString("'=1+1", $csv->streamedContent());
        $this->post('/admin/transfer/export', ['format' => 'print'])->assertOk()->assertSee('Reference catalogue');
    }

    public function test_livewire_health_actions_require_administrator_and_dispatch_probe(): void
    {
        Queue::fake();
        Livewire::actingAs($this->admin)->test(HealthPanel::class)->call('probe')->assertSee('Probe dispatched');
        Queue::assertPushed(HealthHeartbeat::class);
        $staff = User::factory()->create(['status' => 'active']);
        Livewire::actingAs($staff)->test(HealthPanel::class)->assertForbidden();
    }

    public function test_reseeding_preserves_admin_credentials_and_membership(): void
    {
        $before = $this->admin->only(['email', 'password', 'status', 'active_organization_id']);
        $this->seed();
        $this->assertSame($before, $this->admin->fresh()->only(array_keys($before)));
        $this->assertTrue($this->admin->fresh()->hasRole('Administrator'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_excel_import_uses_the_first_workbook_sheet_relationship(): void
    {
        $content = app(TabularFile::class)->xlsx([['category', 'code', 'label'], ['country', 'GH', 'Ghana']]);
        $file = UploadedFile::fake()->createWithContent('renamed-sheet.xlsx', $content);
        $zip = new \ZipArchive;
        $zip->open($file->getRealPath());
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->deleteName('xl/worksheets/sheet1.xml');
        $zip->addFromString('xl/worksheets/sheet7.xml', $sheet);
        $relationships = str_replace('worksheets/sheet1.xml', 'worksheets/sheet7.xml', $zip->getFromName('xl/_rels/workbook.xml.rels'));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $relationships);
        $zip->close();
        $this->assertSame(['country', 'GH', 'Ghana'], app(TabularFile::class)->read($file)['rows'][0]);
    }
}
