<?php

namespace App\Domain\Identity;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Entra
{
    public function ready(): bool
    {
        return (bool) (config('identity.entra.enabled') && Str::isUuid(config('identity.entra.tenant_id')) && Str::isUuid(config('identity.entra.client_id')) && config('identity.entra.client_secret') && filter_var($this->callback(), FILTER_VALIDATE_URL) && str_starts_with($this->callback(), 'https://') && $this->callback() === rtrim(config('app.url'), '/').'/auth/microsoft/callback');
    }

    public function callback(): string
    {
        return config('identity.entra.redirect_uri') ?: route('microsoft.callback');
    }

    public function authorize(array $state): string
    {
        return $this->base().'/oauth2/v2.0/authorize?'.http_build_query([
            'client_id' => config('identity.entra.client_id'), 'response_type' => 'code', 'redirect_uri' => $this->callback(),
            'response_mode' => 'query', 'scope' => 'openid profile email', 'state' => $state['state'], 'nonce' => $state['nonce'],
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $state['verifier'], true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256',
        ]);
    }

    public function exchange(string $code, array $state): array
    {
        $response = Http::asForm()->timeout(10)->withOptions(['allow_redirects' => false])->post($this->base().'/oauth2/v2.0/token', [
            'client_id' => config('identity.entra.client_id'), 'client_secret' => config('identity.entra.client_secret'),
            'code' => $code, 'redirect_uri' => $this->callback(), 'grant_type' => 'authorization_code', 'code_verifier' => $state['verifier'],
        ]);
        if (! $response->successful() || ! is_string($response->json('id_token'))) {
            $this->fail();
        }

        return $this->validateToken($response->json('id_token'), $state['nonce']);
    }

    public function validateToken(string $token, string $nonce): array
    {
        $cacheKey = 'entra-jwks:'.config('identity.entra.tenant_id');
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $jwks = Cache::remember($cacheKey, 3600, fn () => Http::timeout(10)->withOptions(['allow_redirects' => false])->get($this->base().'/discovery/v2.0/keys')->throw()->json());
                // No token-selected algorithm, URL, issuer or key source is trusted.
                $jwks['keys'] = array_values(array_filter($jwks['keys'] ?? [], fn ($key) => ($key['kty'] ?? '') === 'RSA' && ($key['use'] ?? 'sig') === 'sig' && ($key['alg'] ?? 'RS256') === 'RS256'));
                $claims = (array) JWT::decode($token, JWK::parseKeySet($jwks, 'RS256'));
                break;
            } catch (\Throwable $e) {
                Cache::forget($cacheKey);
                if ($attempt === 1) {
                    $this->fail();
                }
            }
        }
        if (($claims['iss'] ?? '') !== $this->base().'/v2.0' || ($claims['tid'] ?? '') !== config('identity.entra.tenant_id') || ($claims['aud'] ?? '') !== config('identity.entra.client_id') || ($claims['ver'] ?? '') !== '2.0' || ! is_string($claims['nonce'] ?? null) || ! hash_equals($nonce, $claims['nonce']) || ! Str::isUuid($claims['oid'] ?? null) || ! isset($claims['exp'], $claims['iat']) || $claims['iat'] > time() + 60 || $claims['exp'] <= time()) {
            $this->fail();
        }

        return $claims;
    }

    private function base(): string
    {
        return 'https://login.microsoftonline.com/'.config('identity.entra.tenant_id');
    }

    private function fail(): never
    {
        throw ValidationException::withMessages(['email' => 'Microsoft sign-in could not be verified. Please start again.']);
    }
}
