<x-layouts.guest title="A little extra security." description="Enter the code from your authenticator app or use an unused recovery code." eyebrow="Verify your sign-in">
<form method="POST" action="{{ route('mfa.verify') }}" class="auth-form">
@csrf
<label for="verification-code">Verification code<input id="verification-code" name="code" required autocomplete="one-time-code" autofocus maxlength="100" placeholder="Authenticator or recovery code"></label><button class="button auth-submit"><span>Verify and sign in</span><x-ui.icon name="shield" /></button></form><a class="auth-back" href="{{ route('login') }}"><x-ui.icon name="arrow" />Start again</a>
</x-layouts.guest>
