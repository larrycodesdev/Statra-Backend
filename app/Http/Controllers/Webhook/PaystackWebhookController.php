<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function __construct(private PaystackService $paystack) {}

    public function handle(Request $request): Response
    {
        $rawPayload = $request->getContent();
        $signature  = $request->header('X-Paystack-Signature', '');

        Log::info('Paystack webhook hit', [
            'sig_present' => !empty($signature),
            'payload_len' => strlen($rawPayload),
        ]);

        if (!$this->paystack->verifyWebhookSignature($rawPayload, $signature)) {
            Log::warning('Paystack webhook signature mismatch', [
                'signature' => $signature,
                'payload'   => $rawPayload,
            ]);
            return response('Unauthorized', 401);
        }

        $payload = json_decode($rawPayload, true);
        $event   = $payload['event'] ?? '';
        $data    = $payload['data'] ?? [];

        Log::info('Paystack webhook received', ['event' => $event]);

        match ($event) {
            'subscription.create'         => $this->handleSubscriptionCreate($data),
            'invoice.payment'             => $this->handleInvoicePayment($data),
            'invoice.payment_failed'      => $this->handleInvoicePaymentFailed($data),
            'subscription.disable'        => $this->handleSubscriptionDisable($data),
            default                       => null,
        };

        return response('OK', 200);
    }

    private function handleSubscriptionCreate(array $data): void
    {
        Log::info('Paystack subscription.create data', ['data' => $data]);

        $email    = $data['customer']['email'] ?? null;
        $user     = $email ? User::where('email', $email)->first() : null;

        if (!$user) {
            Log::warning('Paystack subscription.create: user not found', ['email' => $email]);
            return;
        }

        $nextDate = isset($data['next_payment_date'])
            ? \Carbon\Carbon::parse($data['next_payment_date'])
            : now()->addMonth();

        Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'paystack_subscription_code' => $data['subscription_code'] ?? null,
                'paystack_customer_code'     => $data['customer']['customer_code'] ?? null,
                'paystack_email_token'       => $data['email_token'] ?? null,
                'plan_code'                  => $data['plan']['plan_code'] ?? config('services.paystack.plan_code'),
                'status'                     => 'active',
                'amount'                     => $data['amount'] ?? config('services.paystack.plan_amount', 1000000),
                'currency'                   => 'NGN',
                'starts_at'                  => now(),
                'expires_at'                 => $nextDate,
                'next_billing_at'            => $nextDate,
            ]
        );
    }

    private function handleInvoicePayment(array $data): void
    {
        $email = $data['customer']['email'] ?? null;
        $user  = $email ? User::where('email', $email)->first() : null;

        if (!$user) {
            return;
        }

        $nextDate = isset($data['subscription']['next_payment_date'])
            ? \Carbon\Carbon::parse($data['subscription']['next_payment_date'])
            : now()->addMonth();

        Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status'          => 'active',
                'expires_at'      => $nextDate,
                'next_billing_at' => $nextDate,
            ]
        );

        SubscriptionInvoice::create([
            'user_id'               => $user->id,
            'paystack_invoice_code' => $data['invoice_code'] ?? null,
            'reference'             => $data['reference'] ?? null,
            'amount'                => $data['amount'] ?? 0,
            'currency'              => 'NGN',
            'status'                => 'success',
            'paid_at'               => now(),
        ]);
    }

    private function handleInvoicePaymentFailed(array $data): void
    {
        $email = $data['customer']['email'] ?? null;
        $user  = $email ? User::where('email', $email)->first() : null;

        if (!$user) {
            return;
        }

        Subscription::where('user_id', $user->id)
            ->update(['status' => 'past_due']);

        SubscriptionInvoice::create([
            'user_id'               => $user->id,
            'paystack_invoice_code' => $data['invoice_code'] ?? null,
            'reference'             => $data['reference'] ?? null,
            'amount'                => $data['amount'] ?? 0,
            'currency'              => 'NGN',
            'status'                => 'failed',
            'paid_at'               => null,
        ]);
    }

    private function handleSubscriptionDisable(array $data): void
    {
        $email = $data['customer']['email'] ?? null;
        $user  = $email ? User::where('email', $email)->first() : null;

        if (!$user) {
            return;
        }

        Subscription::where('user_id', $user->id)
            ->update(['status' => 'cancelled']);
    }
}
