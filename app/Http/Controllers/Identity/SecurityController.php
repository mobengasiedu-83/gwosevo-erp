<?php

namespace App\Http\Controllers\Identity;

use App\Domain\Identity\IdentityAudit;
use App\Domain\Identity\LoginFlow;
use App\Domain\Identity\Mfa;
use App\Domain\Identity\SessionRegistry;
use App\Http\Controllers\Controller;
use App\Models\IdentitySession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class SecurityController extends Controller
{
    public function index(Request $request)
    {
        $pending = $request->session()->get('identity.enrollment');
        $secret = $pending && $pending['user_id'] === $request->user()->id && $pending['expires'] > now()->timestamp ? Crypt::decryptString($pending['secret']) : null;

        return response()->view('identity.security', [
            'secret' => $secret,
            'otpUri' => $secret ? app(Google2FA::class)->getQRCodeUrl(config('app.name'), $request->user()->email, $secret) : null,
            'sessions' => IdentitySession::where('user_id', $request->user()->id)->whereNull('revoked_at')->where('last_seen_at', '>=', now()->subMinutes(config('session.lifetime')))->latest('last_seen_at')->get(),
            'currentHash' => hash('sha256', $request->session()->getId()),
            'codes' => $request->session()->get('identity.recovery_display') ? Crypt::decrypt($request->session()->get('identity.recovery_display')) : [],
        ])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function setup(Request $request)
    {
        $request->validate(['current_password' => ['required', 'current_password:web']]);
        abort_if($request->user()->mfa_confirmed_at, 422, 'MFA is already enabled.');
        $request->session()->put('identity.enrollment', ['user_id' => $request->user()->id, 'secret' => Crypt::encryptString(app(Google2FA::class)->generateSecretKey(32)), 'expires' => now()->addMinutes(10)->timestamp]);

        return to_route('security.index');
    }

    public function enroll(Request $request, Mfa $mfa)
    {
        $data = $request->validate(['code' => ['required', 'regex:/^[0-9]{6}$/']]);
        $pending = $request->session()->get('identity.enrollment');
        abort_unless($pending && $pending['user_id'] === $request->user()->id && $pending['expires'] > now()->timestamp, 422, 'Enrollment expired. Start again.');
        $secret = Crypt::decryptString($pending['secret']);
        $timestamp = app(Google2FA::class)->verifyKeyNewer($secret, $data['code'], 0, 1);
        if ($timestamp === false) {
            throw ValidationException::withMessages(['code' => 'Invalid authenticator code.']);
        }
        $codes = $mfa->recoveryCodes();
        DB::transaction(function () use ($request, $secret, $timestamp, $codes) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_if($user->mfa_confirmed_at, 422);
            $user->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => now(), 'mfa_last_used' => $timestamp, 'mfa_recovery_codes' => array_map(fn ($code) => hash('sha256', $code), $codes)])->save();
            IdentityAudit::change('mfa.enabled', $user->id, null, ['enabled' => true]);
        });
        $request->session()->forget('identity.enrollment');
        $request->session()->put('identity.mfa_verified', $request->user()->id);
        $request->session()->flash('identity.recovery_display', Crypt::encrypt($codes));

        return to_route('security.index')->with('success', 'MFA enabled. Save your recovery codes now; they will not be shown again.');
    }

    public function confirm(Request $request, Mfa $mfa)
    {
        $request->validate(['code' => ['required', 'string', 'max:100']]);
        if (! $mfa->verify($request->user(), $request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => 'Invalid or already used code.']);
        }
        $request->session()->put('identity.mfa_verified', $request->user()->id);
        IdentityAudit::login('mfa_success', $request->user());

        return to_route('dashboard');
    }

    public function recovery(Request $request, Mfa $mfa)
    {
        $this->confirmSensitive($request, $mfa);
        $codes = $mfa->recoveryCodes();
        $request->user()->forceFill(['mfa_recovery_codes' => array_map(fn ($code) => hash('sha256', $code), $codes)])->save();
        IdentityAudit::change('mfa.recovery_regenerated', $request->user()->id, null, ['count' => count($codes)]);
        $request->session()->flash('identity.recovery_display', Crypt::encrypt($codes));

        return to_route('security.index')->with('success', 'New recovery codes generated; old codes are invalid.');
    }

    public function disable(Request $request, Mfa $mfa)
    {
        abort_if($request->user()->requiresMfa(), 422, 'MFA is required for your account.');
        $this->confirmSensitive($request, $mfa);
        DB::transaction(function () use ($request) {
            $request->user()->forceFill(['mfa_secret' => null, 'mfa_confirmed_at' => null, 'mfa_last_used' => null, 'mfa_recovery_codes' => null])->save();
            app(SessionRegistry::class)->revokeAll($request->user());
            IdentityAudit::change('mfa.disabled', $request->user()->id, null, ['enabled' => false]);
        });
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }

    public function password(Request $request, Mfa $mfa)
    {
        $request->validate(['current_password' => ['required', 'current_password:web'], 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        if ($request->user()->mfa_confirmed_at) {
            $this->confirmSensitive($request, $mfa);
        }
        DB::transaction(function () use ($request) {
            $request->user()->forceFill(['password' => $request->input('password')])->save();
            app(SessionRegistry::class)->revokeAll($request->user());
            IdentityAudit::change('password.changed', $request->user()->id, null, ['sessions_revoked' => true]);
        });
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('status', 'Password changed. Sign in again.');
    }

    public function revoke(Request $request, IdentitySession $identitySession)
    {
        abort_unless($identitySession->user_id === $request->user()->id, 403);
        abort_unless((int) $request->session()->get('identity.mfa_verified') === $request->user()->id || ! $request->user()->mfa_confirmed_at, 403);
        $identitySession->update(['revoked_at' => now()]);
        IdentityAudit::change('session.revoked', $request->user()->id, null, ['session_id' => $identitySession->id]);

        return to_route('security.index')->with('success', 'Session revoked.');
    }

    public function challenge(Request $request)
    {
        $this->pending($request);

        return response()->view('identity.challenge')->header('Cache-Control', 'no-store');
    }

    public function verifyChallenge(Request $request, Mfa $mfa, LoginFlow $flow)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:100']]);
        [$user, $pending] = $this->pending($request);
        if (! $mfa->verify($user, $data['code'])) {
            IdentityAudit::login('mfa_failed', $user);
            throw ValidationException::withMessages(['code' => 'Invalid or already used code.']);
        }

        return $flow->finish($user->fresh(), $pending['method'], $pending['remember'], true);
    }

    private function pending(Request $request): array
    {
        $pending = $request->session()->get('identity.pending');
        $user = $pending ? User::find($pending['user_id']) : null;
        abort_unless($pending && $pending['expires'] > now()->timestamp && $user?->isActive() && $user->auth_version === $pending['version'], 403, 'Sign-in expired. Start again.');

        return [$user, $pending];
    }

    private function confirmSensitive(Request $request, Mfa $mfa): void
    {
        $data = $request->validate(['current_password' => ['required', 'current_password:web'], 'code' => ['required', 'string', 'max:100']]);
        if (! $mfa->verify($request->user(), $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Invalid or already used code.']);
        }
    }
}
