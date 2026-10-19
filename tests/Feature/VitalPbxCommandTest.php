<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PbxConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VitalPbxCommandTest extends TestCase
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

    public function test_vitalpbx_check_uses_configured_credentials_without_printing_them(): void
    {
        config()->set('vitalpbx', [
            'base_url' => 'https://pbx.example.test',
            'api_key' => 'do-not-print-this-key',
            'tenant' => 'VitalPBX',
            'timeout' => 7,
        ]);

        Http::fake([
            'https://pbx.example.test/api/v2/tenants' => Http::response(['status' => 'success'], 200),
        ]);

        $this->artisan('vitalpbx:check')
            ->expectsOutput('VitalPBX API responded successfully over HTTPS.')
            ->doesntExpectOutput('do-not-print-this-key')
            ->assertExitCode(0);

        Http::assertSent(fn ($request) => $request->url() === 'https://pbx.example.test/api/v2/tenants'
            && $request->hasHeader('app-key', 'do-not-print-this-key')
            && $request->hasHeader('tenant', 'VitalPBX'));
    }

    public function test_vitalpbx_check_requires_credentials_and_https(): void
    {
        config()->set('vitalpbx.base_url', '');
        config()->set('vitalpbx.api_key', '');

        $this->artisan('vitalpbx:check')
            ->expectsOutput('Set VITALPBX_API_BASE_URL and VITALPBX_API_KEY in .env first.')
            ->assertExitCode(1);

        config()->set('vitalpbx.base_url', 'http://pbx.example.test');
        config()->set('vitalpbx.api_key', 'test-key');

        $this->artisan('vitalpbx:check')
            ->expectsOutput('VitalPBX API base URL must use HTTPS.')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_contact_centre_profile_uses_local_env_defaults(): void
    {
        config()->set('vitalpbx.base_url', 'https://pbx.example.test');
        config()->set('vitalpbx.api_key', 'profile-secret');

        $this->actingAsVerified($this->admin)
            ->post('/contact-centre/connections', ['name' => 'Local VitalPBX'])
            ->assertRedirect();

        $profile = PbxConnection::firstOrFail();

        $this->assertSame('https://pbx.example.test', $profile->base_url);
        $this->assertSame('profile-secret', $profile->api_token);
        $this->assertFalse($profile->is_active);
        $this->assertNotEmpty($profile->webhook_secret);
    }
}
