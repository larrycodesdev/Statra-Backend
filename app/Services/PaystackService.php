<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    private string $baseUrl = 'https://api.paystack.co';
    private ?string $secretKey;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');

        Log::info('PaystackService booted', [
            'key_set'    => !empty($this->secretKey),
            'key_prefix' => $this->secretKey ? substr($this->secretKey, 0, 12) . '...' : 'NULL',
            'plan_code'  => config('services.paystack.plan_code'),
            'plan_amount' => config('services.paystack.plan_amount'),
        ]);
    }

    public function initializeSubscription(string $email, string $callbackUrl): ?array
    {
        // No `plan` here — we charge a plain ₦10,000 transaction first.
        // After charge.success fires we call createSubscription() with the
        // customer's authorization_code + start_date = 30 days from now.
        // This gives each subscriber their own billing anchor, not a shared plan anchor.
        $payload = [
            'email'        => $email,
            'amount'       => (int) config('services.paystack.plan_amount', 1000000),
            'callback_url' => $callbackUrl,
            'currency'     => 'NGN',
            'metadata'     => ['purpose' => 'statra_subscription'],
        ];

        Log::info('Paystack init payload', $payload);

        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", $payload);

        Log::info('Paystack init response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if (!$response->successful() || !$response->json('status')) {
            Log::error('Paystack subscription init failed', ['response' => $response->json()]);
            return null;
        }

        return $response->json('data');
    }

    public function createSubscription(string $customerCode, string $authorizationCode): ?array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/subscription", [
                'customer'      => $customerCode,
                'plan'          => config('services.paystack.plan_code'),
                'authorization' => $authorizationCode,
                'start_date'    => now()->addMonth()->toIso8601String(),
            ]);

        Log::info('Paystack create subscription response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if (!$response->successful() || !$response->json('status')) {
            Log::error('Paystack create subscription failed', ['response' => $response->json()]);
            return null;
        }

        return $response->json('data');
    }

    public function cancelSubscription(string $subscriptionCode, string $emailToken): bool
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/subscription/disable", [
                'code'  => $subscriptionCode,
                'token' => $emailToken,
            ]);

        if (!$response->successful() || !$response->json('status')) {
            Log::error('Paystack cancel subscription failed', ['response' => $response->json()]);
            return false;
        }

        return true;
    }

    public function verifyWebhookSignature(string $rawPayload, string $signature): bool
    {
        $expected = hash_hmac('sha512', $rawPayload, $this->secretKey);
        return hash_equals($expected, $signature);
    }
}
