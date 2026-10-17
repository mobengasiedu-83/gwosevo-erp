<x-layouts.app>
<div class="admin-heading"><p>My account</p><h1>Security and sessions</h1><p>{{ auth()->user()->email }}</p></div>
@if(auth()->user()->requiresMfa() && !auth()->user()->mfa_confirmed_at)
<div class="notice">Your account requires an authenticator. Complete enrollment before using the workspace. Your existing password has not changed.</div>
@endif
@if(auth()->user()->mfa_confirmed_at && (int)session('identity.mfa_verified')!==auth()->id())
<section class="panel"><h2>Verify your authenticator</h2><form class="grid-form" method="POST" action="{{ route('security.confirm') }}">
@csrf<label>Authenticator or recovery code<input name="code" autocomplete="one-time-code" required></label><div class="wide"><button class="button">Unlock workspace</button></div></form></section>
@elseif(!auth()->user()->mfa_confirmed_at)
<section class="panel"><h2>Authenticator enrollment</h2>
@if(!$secret)
<p class="help">Confirm your password to generate an authenticator setup key.</p><form class="grid-form" method="POST" action="{{ route('security.setup') }}">
@csrf<label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label><div class="wide"><button class="button">Start authenticator setup</button></div></form>
@else
<p class="help">Add a time-based account in Microsoft Authenticator, Google Authenticator or another TOTP app. Enter your email and the setup key below manually, then confirm a generated code. Setup expires in ten minutes.</p><p class="security-key">{{ $secret }}</p><details><summary>Authenticator setup URI</summary><code class="security-key">{{ $otpUri }}</code></details><form class="grid-form" method="POST" action="{{ route('security.enroll') }}">
@csrf<label>Six-digit authenticator code<input name="code" required pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code"></label><div class="wide"><button class="button">Confirm and enable MFA</button></div></form>
@endif
</section>
@else
<section class="panel"><h2>Authenticator enabled</h2><p class="help">Recovery codes remaining: {{ count(auth()->user()->mfa_recovery_codes ?? []) }}. Each code works once.</p>
@if($codes)
<div class="notice">Store these codes somewhere private. They are shown only on this page load.</div><ul class="security-key">
@foreach($codes as $code)
<li>{{ $code }}</li>
@endforeach
</ul>
@endif
<details class="record-edit"><summary>Replace recovery codes</summary><form class="grid-form" method="POST" action="{{ route('security.recovery') }}">
@csrf<label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label><label>Authenticator or recovery code<input name="code" required autocomplete="one-time-code"></label><div class="wide"><button class="button">Generate new recovery codes</button></div></form></details>
@if(!auth()->user()->requiresMfa())
<details class="record-edit"><summary>Disable optional MFA</summary><form class="grid-form" method="POST" action="{{ route('security.disable') }}" data-confirm="Disable MFA and end your sessions?">
@csrf 
@method('DELETE')<label>Current password<input type="password" name="current_password" required></label><label>Authenticator or recovery code<input name="code" required></label><div class="wide"><button class="button secondary">Disable MFA and sign out</button></div></form></details>
@endif
</section>
@endif
<section class="panel"><h2>Change password</h2><form class="grid-form" method="POST" action="{{ route('security.password') }}">
@csrf 
@method('PUT')<label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
@if(auth()->user()->mfa_confirmed_at)
<label>Authenticator or recovery code<input name="code" required autocomplete="one-time-code"></label>
@endif
<label>New password<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><label>Confirm new password<input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password"></label><p class="help wide">At least 12 characters including uppercase, lowercase, a number and a symbol. Changing your password ends all sessions.</p><div class="wide"><button class="button secondary">Change password and sign out</button></div></form></section>
<section class="panel"><h2>Active sessions</h2><div class="table-scroll"><table class="admin-table"><thead><tr><th>Device</th><th>Last seen</th><th>Actions</th></tr></thead><tbody>
@foreach($sessions as $device)
<tr><td>{{ $device->user_agent }}<p class="help">{{ $device->ip_address }} · {{ $device->method }}</p></td><td>{{ $erpSettings->formatDate($device->last_seen_at,true) }}
@if(hash_equals($currentHash,$device->session_hash))
<span class="status good">Current session</span>
@endif
</td><td><form method="POST" action="{{ route('security.sessions.revoke',$device) }}" data-confirm="Revoke this session? Revoking your current session will sign you out.">
@csrf 
@method('DELETE')<button class="button secondary">Revoke</button></form></td></tr>
@endforeach
</tbody></table></div></section>
</x-layouts.app>
