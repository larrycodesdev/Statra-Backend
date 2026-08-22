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
    }

    public function initializeSubscription(string $email, string $callbackUrl): ?array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", [
                'email'        => $email,
                'amount'       => (int) config('services.paystack.plan_amount', 1000000),
                'plan'         => config('services.paystack.plan_code'),
                'callback_url' => $callbackUrl,
                'currency'     => 'NGN',
            ]);

        if (!$response->successful() || !$response->json('status')) {
            Log::error('Paystack subscription init failed', ['response' => $response->json()]);
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
