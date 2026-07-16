<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class AppleTokenVerifier
{
    private const JWKS_URL = 'https://appleid.apple.com/auth/keys';
    private const ISSUER   = 'https://appleid.apple.com';

    /**
     * Verify an Apple identity token and return its claims.
     * Throws on any validation failure.
     */
    public function verify(string $identityToken): array
    {
        $bundleId = config('services.apple.client_id');

        if (empty($bundleId)) {
            throw new \RuntimeException('APPLE_CLIENT_ID (bundle ID) is not configured.');
        }

        try {
            $keySet  = $this->keySet();
            $payload = (array) JWT::decode($identityToken, $keySet);
        } catch (\Throwable $e) {
            // Clear cached keys and retry once — Apple rotates keys periodically
            Cache::forget('apple_public_jwks');
            $keySet  = $this->keySet();
            $payload = (array) JWT::decode($identityToken, $keySet);
        }

        if (($payload['iss'] ?? '') !== self::ISSUER) {
            throw new \InvalidArgumentException('Apple token has an invalid issuer.');
        }

        if (($payload['aud'] ?? '') !== $bundleId) {
            throw new \InvalidArgumentException('Apple token audience does not match bundle ID.');
        }

        if (empty($payload['sub'])) {
            throw new \InvalidArgumentException('Apple token is missing subject (user ID).');
        }

        return $payload;
    }

    private function keySet(): array
    {
        return Cache::remember('apple_public_jwks', 3600, function () {
            $response = (new Client())->get(self::JWKS_URL);
            $jwks     = json_decode($response->getBody()->getContents(), true);
            return JWK::parseKeySet($jwks);
        });
    }
}
