<?php

namespace App\Domain\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class Turnstile
{
    public function enabled(): bool
    {
        return (bool) config('services.turnstile.enabled');
    }

    public function siteKey(): ?string
    {
        return config('services.turnstile.site_key') ?: null;
    }

    public function assertValid(Request $request): void
    {
        if (! $this->enabled()) {
            return;
        }

        $secret = config('services.turnstile.secret_key');
        $token = $request->input('cf-turnstile-response');
        if (! is_string($secret) || $secret === '' || ! is_string($token) || $token === '') {
            throw ValidationException::withMessages(['turnstile' => 'Please complete the security check and try again.']);
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout((int) config('services.turnstile.timeout', 5))
                ->timeout((int) config('services.turnstile.timeout', 5))
                ->post((string) config('services.turnstile.verify_url'), [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['turnstile' => 'The security check could not be verified. Please try again.']);
        }

        if (! $response->successful() || $response->json('success') !== true) {
            throw ValidationException::withMessages(['turnstile' => 'The security check was not accepted. Please try again.']);
        }
    }
}
