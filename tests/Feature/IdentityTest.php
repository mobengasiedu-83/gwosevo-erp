<?php

namespace Tests\Feature;

use App\Domain\Identity\AccessReviews;
use App\Domain\Identity\ApprovalAuthority;
use App\Domain\Identity\Entra;
use App\Domain\Identity\LeadAccess;
use App\Domain\Identity\Mfa;
use App\Models\AccessReviewItem;
use App\Models\ApprovalDelegation;
use App\Models\Department;
use App\Models\IdentitySession;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationNotification;
use Firebase\JWT\JWT;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IdentityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    private const PASSWORD = 'FixturePass!12345';

    protected function setUp(): void
    {
        parent::setUp();
        config(['identity.mfa_required' => true]);
        $this->seed();
        $this->organization = Organization::where('code', 'GWO-SEVO')->firstOrFail();
        $this->admin = $this->user('Administrator');
    }

    private function user(string $role = 'Sales', string $scope = 'organization', ?Department $department = null, ?Team $team = null): User
    {
        $user = User::factory()->create(['password' => self::PASSWORD, 'status' => 'active', 'active_organization_id' => $this->organization->id]);
        $user->assignRole($role);
        $user->organizations()->attach($this->organization->id, ['scope' => $scope, 'department_id' => $department?->id, 'team_id' => $team?->id]);

        return $user;
    }

    private function accessPayload(User $user, string $status = 'active'): array
    {
        return ['name' => $user->name, 'status' => $status, 'roles' => $user->roles->pluck('name')->all(), 'permissions' => [], 'active_organization_id' => $this->organization->id, 'require_mfa' => 0, 'memberships' => [['organization_id' => $this->organization->id, 'scope' => 'organization']]];
    }

    private function lead(User $owner): Lead
    {
        return Lead::create(['organization_id' => $this->organization->id, 'reference' => 'TEST-'.Str::uuid(), 'first_name' => 'Synthetic', 'last_name' => 'Lead', 'owner_id' => $owner->id, 'created_by' => $owner->id]);
    }

    public function test_login_keeps_existing_password_and_requires_admin_enrollment(): void
    {
        $hash = $this->admin->password;
        $this->post('/login', ['email' => $this->admin->email, 'password' => self::PASSWORD])->assertRedirect(route('security.index'));
        $this->assertAuthenticatedAs($this->admin);
        $this->get('/admin/identity/users')->assertRedirect(route('security.index'));
        $this->get('/account/security')->assertOk()->assertSee('Your account requires an authenticator');
        $this->assertSame($hash, $this->admin->fresh()->password);
        $this->assertDatabaseHas('login_events', ['user_id' => $this->admin->id, 'event' => 'success']);
    }

    public function test_invalid_and_suspended_login_are_denied_without_password_leaks(): void
    {
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'wrong'])->assertSessionHasErrors('email')->assertSessionMissing('_old_input.password');
        $this->assertGuest();
        $this->admin->update(['status' => 'suspended']);
        $this->post('/login', ['email' => $this->admin->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('login_events', ['event' => 'failed']);
    }

    public function test_mfa_enrollment_encrypts_secret_and_recovery_codes_and_requires_confirmation(): void
    {
        $this->post('/login', ['email' => $this->admin->email, 'password' => self::PASSWORD]);
        $this->post('/account/security/mfa/setup', ['current_password' => self::PASSWORD])->assertRedirect();
        $pending = session('identity.enrollment');
        $secret = Crypt::decryptString($pending['secret']);
        $this->assertNull($this->admin->fresh()->mfa_confirmed_at);
        $this->post('/account/security/mfa/enroll', ['code' => 'invalid'])->assertSessionHasErrors('code');
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->post('/account/security/mfa/enroll', ['code' => $code])->assertRedirect();
        $user = $this->admin->fresh();
        $this->assertNotNull($user->mfa_confirmed_at);
        $this->assertSame($secret, $user->mfa_secret);
        $this->assertNotSame($secret, DB::table('users')->where('id', $user->id)->value('mfa_secret'));
        $codes = Crypt::decrypt(session('identity.recovery_display'));
        $this->assertCount(8, $codes);
        $this->assertFalse(app(Mfa::class)->verify($user, $code));
        $this->assertTrue(app(Mfa::class)->verify($user, $codes[0]));
        $this->assertFalse(app(Mfa::class)->verify($user, $codes[0]));
        $this->get('/admin/identity/users')->assertOk();
        $this->delete('/account/security/mfa', ['current_password' => self::PASSWORD, 'code' => $codes[1]])->assertStatus(422);
    }

    public function test_mfa_login_keeps_guest_until_second_factor_succeeds(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $this->admin->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => now(), 'mfa_recovery_codes' => [hash('sha256', 'fixture-recovery')]])->save();
        $this->post('/login', ['email' => $this->admin->email, 'password' => self::PASSWORD])->assertRedirect('/mfa/challenge');
        $this->assertGuest();
        $this->get('/mfa/challenge')->assertOk();
        $this->post('/mfa/challenge', ['code' => 'wrong'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post('/mfa/challenge', ['code' => 'fixture-recovery'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->admin);
        $this->assertDatabaseCount('identity_sessions', 1);
    }

    public function test_invite_accepts_once_and_never_stores_plain_token_or_password(): void
    {
        Notification::fake();
        $this->actingAsVerified($this->admin);
        $payload = ['name' => 'Invited Staff', 'email' => 'invited@example.test', 'roles' => ['Sales'], 'active_organization_id' => $this->organization->id, 'require_mfa' => 0, 'memberships' => [['organization_id' => $this->organization->id, 'scope' => 'own']]];
        $this->post('/admin/identity/users', $payload)->assertRedirect();
        $user = User::where('email', $payload['email'])->firstOrFail();
        $this->assertSame('invited', $user->status);
        $url = null;
        Notification::assertSentTo($user, UserInvitationNotification::class, function ($notification) use ($user, &$url) {
            $url = $notification->toMail($user)->actionUrl;

            return true;
        });
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->assertSame(hash('sha256', $token), UserInvitation::firstOrFail()->token_hash);
        $this->post('/logout');
        $this->get('/invitation/'.$token)->assertOk();
        $this->post('/invitation/'.$token, ['password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertRedirect('/login');
        $this->assertTrue($user->fresh()->isActive());
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
        $this->post('/invitation/'.$token, ['password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertNotFound();
    }

    public function test_password_reset_is_one_time_retains_mfa_and_revokes_sessions(): void
    {
        Notification::fake();
        $user = $this->user();
        $user->forceFill(['mfa_secret' => app(Google2FA::class)->generateSecretKey(32), 'mfa_confirmed_at' => now()])->save();
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect()->assertSessionHas('status');
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $newPassword = 'ReplacementPass!12345';
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => $newPassword, 'password_confirmation' => $newPassword])->assertRedirect('/login');
        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
        $this->assertNotNull($user->fresh()->mfa_confirmed_at);
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => $newPassword, 'password_confirmation' => $newPassword])->assertSessionHasErrors('email');
    }

    public function test_suspension_revokes_sessions_and_tokens_and_last_admin_is_protected(): void
    {
        $staff = $this->user();
        $token = $staff->createToken('Fixture token');
        $this->actingAsVerified($this->admin)->put('/admin/identity/users/'.$staff->id, $this->accessPayload($staff, 'suspended'))->assertRedirect();
        $this->assertSame('suspended', $staff->fresh()->status);
        $this->assertSame(1, $staff->fresh()->auth_version);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $payload = $this->accessPayload($this->admin, 'inactive');
        $this->put('/admin/identity/users/'.$this->admin->id, $payload)->assertSessionHasErrors('roles');
        $this->assertTrue($this->admin->fresh()->isActive());
    }

    public function test_session_registry_revocation_and_version_mismatch_end_access(): void
    {
        $staff = $this->user();
        $login = $this->post('/login', ['email' => $staff->email, 'password' => self::PASSWORD])->assertRedirect();
        $this->withCookie(config('session.cookie'), $login->getCookie(config('session.cookie'))->getValue());
        $this->get('/dashboard')->assertOk();
        $session = IdentitySession::where('user_id', $staff->id)->firstOrFail();
        $session->update(['revoked_at' => now()]);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        $login = $this->post('/login', ['email' => $staff->email, 'password' => self::PASSWORD]);
        $this->withCookie(config('session.cookie'), $login->getCookie(config('session.cookie'))->getValue());
        $staff->increment('auth_version');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_role_edit_invalidates_sessions_and_administrator_role_is_immutable(): void
    {
        $this->actingAsVerified($this->admin)->post('/admin/identity/roles', ['name' => 'Analyst', 'permissions' => ['leads.view'], 'requires_mfa' => 1])->assertRedirect();
        $staff = $this->user('Analyst');
        $role = Role::findByName('Analyst');
        $this->put('/admin/identity/roles/'.$role->id, ['name' => 'Analyst', 'permissions' => ['leads.view', 'leads.export'], 'requires_mfa' => 1])->assertRedirect();
        $this->assertSame(1, $staff->fresh()->auth_version);
        $this->assertTrue($staff->fresh()->requiresMfa());
        $this->delete('/admin/identity/roles/'.$role->id)->assertStatus(422);
        $adminRole = Role::findByName('Administrator');
        $this->put('/admin/identity/roles/'.$adminRole->id, ['name' => 'Renamed', 'permissions' => [], 'requires_mfa' => 0])->assertStatus(422);
    }

    public function test_own_team_department_and_organization_scopes_apply_to_queries_and_direct_urls(): void
    {
        $department = Department::create(['organization_id' => $this->organization->id, 'name' => 'Sales', 'code' => 'SALES']);
        $otherDepartment = Department::create(['organization_id' => $this->organization->id, 'name' => 'Other', 'code' => 'OTHER']);
        $team = Team::create(['organization_id' => $this->organization->id, 'department_id' => $department->id, 'name' => 'A', 'code' => 'A']);
        $otherTeam = Team::create(['organization_id' => $this->organization->id, 'department_id' => $department->id, 'name' => 'B', 'code' => 'B']);
        $owner = $this->user('Sales', 'own', $department, $team);
        $teammate = $this->user('Sales', 'own', $department, $team);
        $colleague = $this->user('Sales', 'own', $department, $otherTeam);
        $outsider = $this->user('Sales', 'own', $otherDepartment);
        $leads = array_map(fn ($user) => $this->lead($user), [$owner, $teammate, $colleague, $outsider]);
        $access = app(LeadAccess::class);
        foreach (['own' => 1, 'team' => 2, 'department' => 3, 'organization' => 4] as $scope => $count) {
            $owner->organizations()->updateExistingPivot($this->organization->id, ['scope' => $scope]);
            $this->assertSame($count, $access->query($owner)->count());
        }
        $owner->organizations()->updateExistingPivot($this->organization->id, ['scope' => 'team']);
        $this->actingAsVerified($owner)->get('/leads/'.$leads[2]->id)->assertForbidden();
        $this->get('/leads')->assertOk()->assertSee($leads[0]->reference)->assertDontSee($leads[2]->reference);
    }

    public function test_membership_validation_rejects_cross_organization_team_assignment(): void
    {
        $other = Organization::create(['code' => 'OTHER', 'name' => 'Other']);
        $team = Team::create(['organization_id' => $other->id, 'name' => 'Other', 'code' => 'OTHER']);
        $staff = $this->user();
        $payload = $this->accessPayload($staff);
        $payload['memberships'][0] = ['organization_id' => $this->organization->id, 'scope' => 'team', 'team_id' => $team->id];
        $this->actingAsVerified($this->admin)->put('/admin/identity/users/'.$staff->id, $payload)->assertSessionHasErrors('memberships');
    }

    public function test_approval_delegation_expires_and_never_expands_record_scope(): void
    {
        $principal = $this->user();
        $principal->givePermissionTo('leads.approve');
        $delegate = $this->user('Sales', 'own');
        $lead = $this->lead($delegate);
        $other = $this->lead($principal);
        $delegation = ApprovalDelegation::create(['organization_id' => $this->organization->id, 'delegator_id' => $principal->id, 'delegate_id' => $delegate->id, 'permission' => 'leads.approve', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(), 'reason' => 'Fixture leave', 'created_by' => $this->admin->id]);
        $authority = app(ApprovalAuthority::class);
        $this->assertSame($principal->id, $authority->principal($delegate, $lead)?->id);
        $this->assertNull($authority->principal($delegate, $other));
        $delegation->update(['ends_at' => now()->subSecond()]);
        $this->assertNull($authority->principal($delegate, $lead));
    }

    public function test_access_review_decisions_apply_atomically_and_lock_completed_review(): void
    {
        $staff = $this->user();
        $this->actingAsVerified($this->admin);
        $review = app(AccessReviews::class)->create('Fixture review', $this->admin, $this->admin, now()->addDay()->toDateTimeString());
        foreach (AccessReviewItem::where('access_review_id', $review->id)->get() as $item) {
            $this->put('/identity/reviews/'.$review->id.'/items/'.$item->id, ['decision' => $item->user_id === $staff->id ? 'revoke' : 'retain', 'notes' => 'Fixture evidence'])->assertRedirect();
        }
        $this->post('/identity/reviews/'.$review->id.'/complete')->assertRedirect();
        $this->assertSame('inactive', $staff->fresh()->status);
        $this->assertTrue($staff->fresh()->roles->isEmpty());
        $this->assertNotNull($review->fresh()->completed_at);
        $this->post('/identity/reviews/'.$review->id.'/complete')->assertStatus(422);
    }

    public function test_access_review_rejects_stale_snapshots_and_unassigned_reviewers(): void
    {
        $staff = $this->user();
        $this->actingAsVerified($this->admin);
        $review = app(AccessReviews::class)->create('Fixture review', $this->admin, $this->admin, now()->addDay()->toDateTimeString());
        AccessReviewItem::where('access_review_id', $review->id)->update(['decision' => 'retain', 'notes' => 'Fixture evidence']);
        $staff->givePermissionTo('leads.export');
        $this->post('/identity/reviews/'.$review->id.'/complete')->assertSessionHasErrors('review');
        $this->assertNull($review->fresh()->completed_at);
        $this->actingAsVerified($staff)->get('/identity/reviews/'.$review->id)->assertForbidden();
    }

    public function test_periodic_review_command_does_not_duplicate_open_reviews(): void
    {
        $this->artisan('identity:review-due')->assertSuccessful();
        $this->artisan('identity:review-due')->assertSuccessful();
        $this->assertDatabaseCount('access_reviews', 1);
    }

    public function test_identity_screens_render_and_staff_cannot_manage_users(): void
    {
        $this->actingAsVerified($this->admin);
        foreach (['/admin/identity/users', '/admin/identity/users/create', '/admin/identity/users/'.$this->admin->id.'/edit', '/admin/identity/roles', '/admin/identity/activity', '/admin/identity/integrations', '/identity/delegations', '/identity/reviews', '/account/security'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->actingAsVerified($this->user())->get('/admin/identity/users')->assertForbidden();
    }

    private function configureEntra(): void
    {
        config(['app.url' => 'https://localhost/gwosevo-erp/public', 'identity.entra.enabled' => true, 'identity.entra.tenant_id' => '11111111-1111-4111-8111-111111111111', 'identity.entra.client_id' => '22222222-2222-4222-8222-222222222222', 'identity.entra.client_secret' => 'fixture-secret', 'identity.entra.redirect_uri' => 'https://localhost/gwosevo-erp/public/auth/microsoft/callback']);
    }

    private function signedEntraToken(array $overrides = []): string
    {
        static $key;
        $key ??= openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $details = openssl_pkey_get_details($key);
        $encode = fn ($value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        Http::fake(['*/discovery/v2.0/keys' => Http::response(['keys' => [['kty' => 'RSA', 'kid' => 'fixture-key', 'alg' => 'RS256', 'use' => 'sig', 'n' => $encode($details['rsa']['n']), 'e' => $encode($details['rsa']['e'])]]])]);
        $claims = ['iss' => 'https://login.microsoftonline.com/'.config('identity.entra.tenant_id').'/v2.0', 'tid' => config('identity.entra.tenant_id'), 'aud' => config('identity.entra.client_id'), 'nonce' => 'fixture-nonce', 'oid' => '33333333-3333-4333-8333-333333333333', 'ver' => '2.0', 'iat' => time() - 1, 'nbf' => time() - 1, 'exp' => time() + 300];

        return JWT::encode([...$claims, ...$overrides], $key, 'RS256', 'fixture-key');
    }

    public function test_entra_validates_signature_issuer_audience_tenant_nonce_and_expiry(): void
    {
        $this->configureEntra();
        $entra = app(Entra::class);
        $valid = $this->signedEntraToken();
        $this->assertSame('33333333-3333-4333-8333-333333333333', $entra->validateToken($valid, 'fixture-nonce')['oid']);
        foreach (['iss' => 'https://untrusted.example', 'aud' => 'wrong', 'tid' => 'wrong', 'nonce' => 'wrong', 'exp' => time() - 1] as $key => $value) {
            try {
                $entra->validateToken($this->signedEntraToken([$key => $value]), 'fixture-nonce');
                $this->fail('Accepted invalid '.$key);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('email', $e->errors());
            }
        }
    }

    public function test_microsoft_login_uses_pkce_and_rejects_forged_state(): void
    {
        $this->configureEntra();
        $response = $this->get('/auth/microsoft')->assertRedirect();
        $this->assertStringContainsString('code_challenge_method=S256', $response->headers->get('Location'));
        $this->assertStringContainsString('response_type=code', $response->headers->get('Location'));
        $this->get('/auth/microsoft/callback?state=forged&code=fake')->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull(session('identity.microsoft'));
    }

    public function test_reseeding_preserves_user_credentials_and_configured_business_permissions(): void
    {
        $hash = $this->admin->password;
        Role::findByName('Sales')->syncPermissions(['leads.view']);
        $this->seed();
        $this->assertSame($hash, $this->admin->fresh()->password);
        $this->assertSame(['leads.view'], Role::findByName('Sales')->permissions->pluck('name')->all());
    }

    public function test_verified_microsoft_callback_authenticates_only_explicit_object_id_links(): void
    {
        $this->configureEntra();
        $jwt = $this->signedEntraToken(['email' => $this->admin->email]);
        Http::fake(['*/oauth2/v2.0/token' => Http::response(['id_token' => $jwt])]);
        $state = ['state' => 'fixture-state', 'nonce' => 'fixture-nonce', 'verifier' => 'fixture-verifier', 'expires' => now()->addMinutes(5)->timestamp];
        $this->withSession(['identity.microsoft' => $state])->get('/auth/microsoft/callback?state=fixture-state&code=fixture-code')->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->admin->forceFill(['entra_tenant_id' => config('identity.entra.tenant_id'), 'entra_object_id' => '33333333-3333-4333-8333-333333333333'])->save();
        $this->withSession(['identity.microsoft' => $state])->get('/auth/microsoft/callback?state=fixture-state&code=fixture-code')->assertRedirect(route('security.index'));
        $this->assertAuthenticatedAs($this->admin);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/oauth2/v2.0/token') && $request['code_verifier'] === 'fixture-verifier' && $request['redirect_uri'] === config('identity.entra.redirect_uri'));
    }

    public function test_expired_mfa_challenge_and_revoked_pending_login_are_rejected(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $this->admin->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => now()])->save();
        $this->post('/login', ['email' => $this->admin->email, 'password' => self::PASSWORD]);
        $pending = session('identity.pending');
        $pending['expires'] = now()->subSecond()->timestamp;
        $this->withSession(['identity.pending' => $pending])->post('/mfa/challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertForbidden();
        $pending['expires'] = now()->addMinute()->timestamp;
        $this->admin->increment('auth_version');
        $this->withSession(['identity.pending' => $pending])->post('/mfa/challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertForbidden();
        $this->assertGuest();
    }

    public function test_expired_invitation_and_weak_password_cannot_activate_user(): void
    {
        $user = $this->user();
        $user->update(['status' => 'invited']);
        $invitation = UserInvitation::create(['user_id' => $user->id, 'token_hash' => hash('sha256', 'fixture-invitation'), 'expires_at' => now()->subSecond(), 'invited_by' => $this->admin->id]);
        $this->get('/invitation/fixture-invitation')->assertNotFound();
        $invitation->update(['expires_at' => now()->addHour()]);
        $this->post('/invitation/fixture-invitation', ['password' => 'weak', 'password_confirmation' => 'weak'])->assertSessionHasErrors('password');
        $this->assertSame('invited', $user->fresh()->status);
    }

    public function test_other_admin_can_reset_lost_mfa_without_changing_user_password(): void
    {
        $staff = $this->user();
        $staff->forceFill(['mfa_secret' => app(Google2FA::class)->generateSecretKey(32), 'mfa_confirmed_at' => now()])->save();
        $this->admin->forceFill(['mfa_secret' => app(Google2FA::class)->generateSecretKey(32), 'mfa_confirmed_at' => now(), 'mfa_recovery_codes' => [hash('sha256', 'fixture-admin-recovery')]])->save();
        $before = $staff->password;
        $this->actingAsVerified($this->admin)->post('/admin/identity/users/'.$staff->id.'/mfa-reset', ['current_password' => self::PASSWORD, 'code' => 'fixture-admin-recovery', 'reason' => 'Verified fixture identity through approved process'])->assertRedirect();
        $this->assertNull($staff->fresh()->mfa_confirmed_at);
        $this->assertTrue($staff->fresh()->require_mfa);
        $this->assertSame($before, $staff->fresh()->password);
        $this->assertSame(1, $staff->fresh()->auth_version);
        $this->assertDatabaseHas('audit_events', ['event' => 'identity.mfa.admin_reset']);
    }

    public function test_session_revocation_cannot_target_another_user_session(): void
    {
        $staff = $this->user();
        $other = $this->user();
        $device = IdentitySession::create(['user_id' => $other->id, 'session_hash' => hash('sha256', 'fixture-other-session'), 'last_seen_at' => now()]);
        $this->actingAsVerified($staff)->delete('/account/security/sessions/'.$device->id)->assertForbidden();
        $this->assertNull($device->fresh()->revoked_at);
    }

    public function test_organization_switching_rejects_nonmember_organizations(): void
    {
        $other = Organization::create(['code' => 'SECOND', 'name' => 'Second organization']);
        $staff = $this->user();
        $this->actingAsVerified($staff)->post('/account/organization', ['organization_id' => $other->id])->assertForbidden();
        $staff->organizations()->attach($other->id, ['scope' => 'own']);
        $this->post('/account/organization', ['organization_id' => $other->id])->assertRedirect('/dashboard');
        $this->assertSame($other->id, $staff->fresh()->active_organization_id);
    }

    public function test_final_administrator_revocation_rolls_back_entire_review(): void
    {
        $staff = $this->user();
        $this->actingAsVerified($this->admin);
        $review = app(AccessReviews::class)->create('Protected review', $this->admin, $this->admin, now()->addDay()->toDateTimeString());
        AccessReviewItem::where('access_review_id', $review->id)->update(['decision' => 'revoke', 'notes' => 'Fixture evidence']);
        $this->post('/identity/reviews/'.$review->id.'/complete')->assertSessionHasErrors('roles');
        $this->assertTrue($staff->fresh()->isActive());
        $this->assertTrue($this->admin->fresh()->isActive());
        $this->assertNull($review->fresh()->completed_at);
    }

    public function test_reset_delivery_failure_does_not_reveal_account_existence(): void
    {
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('Fixture SMTP outage'));
        $existing = $this->post('/forgot-password', ['email' => $this->admin->email])->assertRedirect()->assertSessionHas('status');
        $message = session('status');
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertRedirect()->assertSessionHas('status', $message);
        $this->assertDatabaseHas('login_events', ['event' => 'password_reset_delivery_failed']);
    }

    public function test_mandatory_enrollment_can_be_disabled_without_changing_credentials(): void
    {
        config(['identity.mfa_required' => false]);
        $before = $this->admin->password;
        $this->assertFalse($this->admin->requiresMfa());
        $this->post('/login', ['email' => $this->admin->email, 'password' => self::PASSWORD])->assertRedirect('/dashboard');
        $this->get('/admin/identity/users')->assertOk();
        $this->get('/account/security')->assertOk()->assertDontSee('Your account requires an authenticator');
        $this->assertSame($before, $this->admin->fresh()->password);
    }

    public function test_disabling_required_enrollment_preserves_existing_authenticator_challenges(): void
    {
        config(['identity.mfa_required' => false]);
        $this->admin->forceFill(['mfa_secret' => app(Google2FA::class)->generateSecretKey(32), 'mfa_confirmed_at' => now()])->save();
        $this->post('/login', ['email' => $this->admin->email, 'password' => self::PASSWORD])->assertRedirect('/mfa/challenge');
        $this->assertGuest();
    }

    public function test_failed_invitation_opens_existing_account_for_resending(): void
    {
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('Fixture delivery outage'));
        $this->actingAsVerified($this->admin);
        $payload = ['name' => 'Invited Staff', 'email' => 'retry@example.test', 'roles' => ['Sales'], 'active_organization_id' => $this->organization->id, 'require_mfa' => 0, 'memberships' => [['organization_id' => $this->organization->id, 'scope' => 'own']]];
        $response = $this->post('/admin/identity/users', $payload);
        $account = User::where('email', 'retry@example.test')->firstOrFail();
        $response->assertRedirect(route('identity.users.edit', $account))->assertSessionHasErrors('email');
        $this->assertSame('invited', $account->status);
        $this->assertNotNull(UserInvitation::where('user_id', $account->id)->firstOrFail()->revoked_at);
    }

    public function test_quick_navigation_respects_role_and_module_access(): void
    {
        $staff = $this->user();
        $this->actingAsVerified($staff)->get('/dashboard')->assertOk()->assertSee('data-command-open', false)->assertDontSee('data-navigation-label="Users &amp; access"', false);
        $this->actingAsVerified($this->admin)->get('/dashboard')->assertSee('data-navigation-label="Users &amp; access"', false);
        $this->put('/admin/modules/crm', ['enabled' => 0]);
        $this->get('/dashboard')->assertDontSee('data-navigation-label="CRM leads"', false);
    }
}
