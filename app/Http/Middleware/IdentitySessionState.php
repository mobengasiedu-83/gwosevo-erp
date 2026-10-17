<?php

namespace App\Http\Middleware;

use App\Domain\Identity\IdentityAudit;
use App\Domain\Identity\LoginFlow;
use App\Domain\Identity\SessionRegistry;
use App\Models\IdentitySession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IdentitySessionState
{
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->user()) {
            $user = $user->fresh();
            abort_unless($user, 403);
            Auth::setUser($user);
            $session = IdentitySession::where('session_hash', hash('sha256', $request->session()->getId()))->first();
            $version = $request->session()->get('identity.auth_version');
            if ($version === null && Auth::viaRemember() && $user->isActive()) {
                Auth::logoutCurrentDevice();

                return app(LoginFlow::class)->begin($user, 'remember', true);
            }
            if ($version === null || ($session && $session->user_id !== $user->id) || ! $user->isActive() || ($version !== null && (int) $version !== $user->auth_version) || $session?->revoked_at) {
                IdentityAudit::login('session_rejected', $user);
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return to_route('login')->withErrors(['email' => 'Your session has ended. Please sign in again.']);
            }
            $request->session()->put('identity.auth_version', $user->auth_version);
            if (! $session) {
                app(SessionRegistry::class)->record($user, 'session');
            } else {
                $session->update(['last_seen_at' => now()]);
            }
            $exempt = $request->routeIs('security.*', 'logout', 'branding.logo');
            if (! $exempt && ($user->requiresMfa() || $user->mfa_confirmed_at) && (int) $request->session()->get('identity.mfa_verified') !== $user->id) {
                return to_route('security.index');
            }
        }

        return $next($request);
    }
}
