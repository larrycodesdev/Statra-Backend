<?php

namespace App\Services;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class FcmService
{
    private const SCOPE         = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_URL     = 'https://oauth2.googleapis.com/token';
    private const FCM_URL       = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    private const GRANT_TYPE    = 'urn:ietf:params:oauth:grant-type:jwt-bearer';

    private array $creds;

    public function __construct()
    {
        $b64 = config('services.firebase.credentials_json');

        if (empty($b64)) {
            throw new \RuntimeException('FIREBASE_CREDENTIALS_JSON is not set.');
        }

        $decoded = base64_decode($b64, strict: true);
        if ($decoded === false) {
            throw new \RuntimeException('FIREBASE_CREDENTIALS_JSON is not valid base64.');
        }

        $this->creds = json_decode($decoded, true)
            ?? throw new \RuntimeException('FIREBASE_CREDENTIALS_JSON decoded to invalid JSON.');
    }

    public function send(string $token, array $notification, array $data = []): void
    {
        $accessToken = $this->accessToken();
        $url         = sprintf(self::FCM_URL, $this->creds['project_id']);

        (new Client())->post($url, [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'message' => [
                    'token'        => $token,
                    'notification' => [
                        'title' => $notification['title'],
                        'body'  => $notification['body'],
                    ],
                    // FCM data values must all be strings
                    'data'    => array_map('strval', $data),
                    'android' => [
                        'priority'     => 'high',
                        'notification' => ['sound' => 'default'],
                    ],
                    'apns' => [
                        'payload' => ['aps' => ['sound' => 'default']],
                    ],
                ],
            ],
        ]);
    }

    private function accessToken(): string
    {
        // Cache for 58 minutes — Google tokens last 60, gives 2-min buffer
        return Cache::remember('fcm_access_token', 3480, function () {
            $now = time();

            $jwt = JWT::encode([
                'iss'   => $this->creds['client_email'],
                'scope' => self::SCOPE,
                'aud'   => self::TOKEN_URL,
                'iat'   => $now,
                'exp'   => $now + 3600,
            ], $this->creds['private_key'], 'RS256');

            $response = (new Client())->post(self::TOKEN_URL, [
                'form_params' => [
                    'grant_type' => self::GRANT_TYPE,
                    'assertion'  => $jwt,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true)['access_token'];
        });
    }
}
