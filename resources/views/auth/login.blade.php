<x-layouts.guest title="Welcome back." :description="$erpSettings->get('login_message')" eyebrow="Sign in to your workspace">
<form method="POST" action="{{ route('login') }}" class="auth-form">
@csrf
<label for="login-email">Work email<input id="login-email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="you@gwosevo.com"></label>
<label for="login-password">Password<div class="password-control"><input id="login-password" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password"><button type="button" class="icon-button" data-password-toggle="login-password" aria-label="Show password" aria-pressed="false"><x-ui.icon name="eye" /></button></div></label>
<div class="auth-options"><label><input type="checkbox" name="remember" value="1" @checked(old('remember'))>Remember me</label><a href="{{ route('password.request') }}">Forgot password?</a></div>
<x-turnstile />
<button class="button auth-submit" type="submit"><span>Sign in</span><x-ui.icon name="arrow" /></button>
@if(app(\App\Domain\Identity\Entra::class)->ready())
<div class="auth-divider">or continue with</div><a class="auth-microsoft" href="{{ route('microsoft.redirect') }}"><span class="microsoft-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>Sign in with Microsoft</a>
@endif
</form><p class="auth-security-note"><x-ui.icon name="shield" />Your workspace. Protected and connected.</p>
</x-layouts.guest>
