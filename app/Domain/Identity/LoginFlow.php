<?php

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class LoginFlow
{
    public function begin(User $user, string $method = 'password', bool $remember = false)
    {
        abort_unless($user->isActive(), 403);
        request()->session()->regenerate();
        if ($user->mfa_confirmed_at) {
            request()->session()->put('identity.pending', ['user_id' => $user->id, 'version' => $user->auth_version, 'expires' => now()->addMinutes(5)->timestamp, 'remember' => $remember, 'method' => $method]);
            IdentityAudit::login('mfa_required', $user, method: $method);

            return to_route('mfa.challenge');
        }

        return $this->finish($user, $method, $remember, false);
    }

    public function finish(User $user, string $method, bool $remember, bool $mfa)
    {
        abort_unless($user->isActive(), 403);
        Auth::login($user, $remember);
        request()->session()->regenerate();
        request()->session()->forget('identity.pending');
        request()->session()->put('identity.auth_version', $user->auth_version);
        request()->session()->put('identity.mfa_verified', $mfa ? $user->id : null);
        app(SessionRegistry::class)->record($user, $method);
        IdentityAudit::login('success', $user, method: $method);
        if ($user->requiresMfa() && ! $mfa) {
            return to_route('security.index');
        }

        return redirect()->intended(route('dashboard'));
    }
}
