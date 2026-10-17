<x-layouts.guest title="We’ll be right back." :description="$message" eyebrow="Workspace maintenance">
<div class="notice"><x-ui.icon name="clock" />The team is taking a moment to improve your workspace.</div><a class="button auth-submit" href="{{ route('login') }}"><span>Administrator sign in</span><x-ui.icon name="arrow" /></a>
</x-layouts.guest>
