<x-layouts.guest :title="$mode==='forgot' ? 'Forgot your password?' : ($mode==='invitation' ? 'You’re invited.' : 'A fresh start.')" :description="$mode==='forgot' ? 'Enter your work email and we’ll help you get back to your workspace.' : ($mode==='invitation' ? 'Set your password to activate your GWO SEVO account.' : 'Choose a strong password to secure your account.')" eyebrow="Account access">
<form method="POST" action="{{ $mode==='forgot' ? route('password.email') : ($mode==='invitation' ? route('invitation.accept',$token) : route('password.update')) }}" class="auth-form">
@csrf
@if($mode==='reset')<input type="hidden" name="token" value="{{ $token }}">
@endif
@if($mode!=='invitation')
<label for="reset-email">Work email<input id="reset-email" type="email" name="email" value="{{ old('email',$email ?? '') }}" required autocomplete="email" placeholder="you@gwosevo.com"></label>
@else
<p class="notice">{{ $email }}</p>
@endif
@if($mode!=='forgot')
<label for="new-password">New password<div class="password-control"><input id="new-password" name="password" type="password" required minlength="12" autocomplete="new-password" placeholder="Create a strong password"><button type="button" class="icon-button" data-password-toggle="new-password" aria-label="Show password" aria-pressed="false"><x-ui.icon name="eye" /></button></div></label>
<label for="confirm-password">Confirm password<input id="confirm-password" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" placeholder="Enter your password again"></label><p class="help">At least 12 characters with uppercase and lowercase letters, numbers and symbols.</p>
@endif
<button class="button auth-submit"><span>{{ $mode==='forgot' ? 'Send reset link' : 'Set password' }}</span><x-ui.icon name="arrow" /></button></form><a class="auth-back" href="{{ route('login') }}"><x-ui.icon name="arrow" />Back to sign in</a>
</x-layouts.guest>
