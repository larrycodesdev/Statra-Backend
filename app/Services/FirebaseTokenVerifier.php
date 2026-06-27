<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class FirebaseTokenVerifier
{
    private const CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';
    private const ISS_PREFIX = 'https://securetoken.google.com/';

    /**
     * Verify a Firebase ID token and return its claims.
     * Throws \RuntimeException or \InvalidArgumentException on failure.
     */
    public function verify(string $idToken): array
    {
        $projectId = $this->projectId();

        // Firebase ID tokens are RS256 JWTs; the kid in the header selects the cert
        $segments = explode('.', $idToken);
        if (count($segments) !== 3) {
            throw new \InvalidArgumentException('Malformed Firebase ID token.');
        }

        $headerJson = base64_decode(strtr($segments[0], '-_', '+/'));
        $header     = json_decode($headerJson, true);
        $kid        = $header['kid'] ?? null;

        if (!$kid) {
            throw new \InvalidArgumentException('Firebase token is missing the key ID (kid).');
        }

        $certs = $this->publicCerts();

        if (!isset($certs[$kid])) {
            // Public keys rotate every few hours — clear cache and retry once
            Cache::forget('firebase_public_certs');
            $certs = $this->publicCerts();
        }

        if (!isset($certs[$kid])) {
            throw new \InvalidArgumentException('Firebase token has an unknown key ID.');
        }

        $payload = (array) JWT::decode($idToken, new Key($certs[$kid], 'RS256'));

        // Validate required claims
        if (($payload['iss'] ?? '') !== self::ISS_PREFIX . $projectId) {
            throw new \InvalidArgumentException('Firebase token has an invalid issuer.');
        }

        if (($payload['aud'] ?? '') !== $projectId) {
            throw new \InvalidArgumentException('Firebase token has an invalid audience.');
        }

        if (empty($payload['sub'])) {
            throw new \InvalidArgumentException('Firebase token is missing subject (uid).');
        }

        return $payload;
    }

    private function publicCerts(): array
    {
        // Cache for 1 hour — Google rotates these every ~6h so this is safe
        return Cache::remember('firebase_public_certs', 3600, function () {
            $response = (new Client())->get(self::CERTS_URL);
            return json_decode($response->getBody()->getContents(), true);
        });
    }

    private function projectId(): string
    {
        $b64 = config('services.firebase.credentials_json');
        return json_decode(base64_decode($b64), true)['project_id'];
    }
}
