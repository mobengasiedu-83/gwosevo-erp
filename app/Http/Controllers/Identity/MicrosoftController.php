<?php

namespace App\Http\Controllers\Identity;

use App\Domain\Identity\Entra;
use App\Domain\Identity\IdentityAudit;
use App\Domain\Identity\LoginFlow;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MicrosoftController extends Controller
{
    public function redirect(Request $request, Entra $entra)
    {
        abort_unless($entra->ready(), 503, 'Microsoft sign-in is not configured.');
        $request->session()->regenerate();
        $state = ['state' => Str::random(64), 'nonce' => Str::random(64), 'verifier' => Str::random(96), 'expires' => now()->addMinutes(5)->timestamp];
        $request->session()->put('identity.microsoft', $state);

        return redirect()->away($entra->authorize($state));
    }

    public function callback(Request $request, Entra $entra, LoginFlow $flow)
    {
        $state = $request->session()->pull('identity.microsoft');
        if (! $entra->ready() || ! $state || $state['expires'] < now()->timestamp || ! is_string($request->input('state')) || ! hash_equals($state['state'], $request->input('state')) || ! is_string($request->input('code')) || $request->filled('error')) {
            IdentityAudit::login('sso_failed', method: 'microsoft');

            return to_route('login')->withErrors(['email' => 'Microsoft sign-in expired or was declined. Start again.']);
        }
        try {
            $claims = $entra->exchange($request->input('code'), $state);
        } catch (\Throwable $e) {
            IdentityAudit::login('sso_failed', method: 'microsoft');

            return to_route('login')->withErrors(['email' => 'Microsoft sign-in could not be verified. Start again.']);
        }
        // Link by tenant + immutable object ID only. Never auto-link using email.
        $user = User::where('entra_tenant_id', $claims['tid'])->where('entra_object_id', $claims['oid'])->first();
        if (! $user?->isActive()) {
            IdentityAudit::login('sso_unlinked_or_inactive', $user, method: 'microsoft');

            return to_route('login')->withErrors(['email' => 'Your Microsoft identity is not linked to an active ERP account. Contact an administrator.']);
        }

        return $flow->begin($user, 'microsoft');
    }
}
