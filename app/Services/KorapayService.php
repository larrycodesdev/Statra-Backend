<?php

namespace App\Services;

use App\Models\BandOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KorapayService
{
    private string $baseUrl;
    private ?string $secretKey;
    private ?string $encryptionKey;

    public function __construct()
    {
        $this->baseUrl       = config('services.korapay.base_url');
        $this->secretKey     = config('services.korapay.secret_key');
        $this->encryptionKey = config('services.korapay.encryption_key');
    }

    public function initializeCheckout(BandOrder $order): ?string
    {
        if (!$this->secretKey) {
            Log::error('Korapay KORAPAY_SECRET_KEY is not set in environment.');
            return null;
        }

        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/charges/initialize", [
                'reference'        => $order->order_number,
                'amount'           => $order->total,
                'currency'         => 'USD',
                'customer'         => [
                    'name'  => "{$order->first_name} {$order->last_name}",
                    'email' => $order->email,
                ],
                'redirect_url'     => rtrim(config('app.frontend_store_url'), '/') . '/order-confirmed?ref=' . $order->order_number,
                'notification_url' => url('/api/v1/store/payment/webhook'),
            ]);

        if (!$response->successful()) {
            Log::error('Korapay checkout initialization failed', [
                'order'    => $order->order_number,
                'response' => $response->json(),
            ]);
            return null;
        }

        return $response->json('data.checkout_url');
    }

    public function verifyWebhookSignature(string $rawPayload, string $signature): bool
    {
        if (!$this->encryptionKey) {
            Log::error('Korapay KORAPAY_ENCRYPTION_KEY is not set in environment.');
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $this->encryptionKey);
        return hash_equals($expected, $signature);
    }
}
