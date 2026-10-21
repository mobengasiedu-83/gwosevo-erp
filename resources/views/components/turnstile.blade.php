@if(app(\App\Domain\Security\Turnstile::class)->enabled() && request()->routeIs('login', 'careers.show'))
    @if(app(\App\Domain\Security\Turnstile::class)->siteKey())
        <div class="cf-turnstile" data-sitekey="{{ app(\App\Domain\Security\Turnstile::class)->siteKey() }}"></div>
        <script>
            (() => {
                const widget = document.currentScript.previousElementSibling;
                const form = widget.closest('form') || [...document.forms].find((candidate) => candidate.compareDocumentPosition(widget) & Node.DOCUMENT_POSITION_FOLLOWING);
                if (form && !widget.closest('form')) {
                    if (form.querySelector('.cf-turnstile')) widget.remove();
                    else form.append(widget);
                }
            })();
        </script>
        <script>
            (() => {
                const widget = document.currentScript.previousElementSibling;
                const form = widget.closest('form') || [...document.forms].find((candidate) => candidate.compareDocumentPosition(widget) & Node.DOCUMENT_POSITION_FOLLOWING);
                if (form && !widget.closest('form')) {
                    if (form.querySelector('.cf-turnstile')) widget.remove();
                    else form.append(widget);
                }
            })();
        </script>
        <script>
            (() => {
                const widget = document.currentScript.previousElementSibling;
                const form = widget.closest('form') || [...document.forms].find((candidate) => candidate.compareDocumentPosition(widget) & Node.DOCUMENT_POSITION_FOLLOWING);
                if (form && !widget.closest('form')) {
                    if (form.querySelector('.cf-turnstile')) widget.remove();
                    else form.append(widget);
                }
            })();
        </script>
        @once
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @endonce
    @else
        <p class="text-sm text-red-700">Security verification is not configured. Please contact the administrator.</p>
    @endif
@endif
