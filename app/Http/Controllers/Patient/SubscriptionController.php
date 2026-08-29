<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function __construct(private PaystackService $paystack) {}

    // GET /api/v1/patient/subscription/status
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_tester) {
            return response()->json([
                'success'     => true,
                'data'        => [
                    'subscribed'       => true,
                    'is_tester'        => true,
                    'status'           => 'active',
                    'expires_at'       => null,
                    'next_billing_at'  => null,
                ],
            ]);
        }

        $sub = $user->subscription;

        if (!$sub) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'subscribed'      => false,
                    'is_tester'       => false,
                    'status'          => 'none',
                    'expires_at'      => null,
                    'next_billing_at' => null,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'subscribed'      => $sub->isActive(),
                'is_tester'       => false,
                'status'          => $sub->status,
                'expires_at'      => $sub->expires_at?->toDateTimeString(),
                'next_billing_at' => $sub->next_billing_at?->toDateTimeString(),
            ],
        ]);
    }

    // GET /api/v1/patient/subscription/billing-history
    public function billingHistory(Request $request): JsonResponse
    {
        $invoices = $request->user()
            ->subscriptionInvoices()
            ->orderByDesc('paid_at')
            ->get(['id', 'amount', 'currency', 'status', 'paid_at', 'reference']);

        $formatted = $invoices->map(fn ($inv) => [
            'id'        => $inv->id,
            'amount'    => $inv->amount / 100, // kobo → naira
            'currency'  => $inv->currency,
            'status'    => $inv->status,
            'paid_at'   => $inv->paid_at?->toDateTimeString(),
            'reference' => $inv->reference,
        ]);

        return response()->json(['success' => true, 'data' => $formatted]);
    }

    // POST /api/v1/patient/subscription/cancel
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        $sub  = $user->subscription;

        if (!$sub || !in_array($sub->status, ['active', 'past_due'])) {
            return response()->json(['success' => false, 'message' => 'No active subscription to cancel.'], 422);
        }

        if (!$sub->paystack_subscription_code || !$sub->paystack_email_token) {
            return response()->json(['success' => false, 'message' => 'Subscription details incomplete. Contact support.'], 422);
        }

        $cancelled = $this->paystack->cancelSubscription(
            $sub->paystack_subscription_code,
            $sub->paystack_email_token
        );

        if (!$cancelled) {
            return response()->json(['success' => false, 'message' => 'Could not cancel subscription. Please try again.'], 502);
        }

        // Webhook will confirm, but update locally immediately for UX
        $sub->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => 'Subscription cancelled successfully.']);
    }

    // POST /api/v1/patient/subscription/checkout
    // Website calls this when user is already logged in — returns Paystack payment URL directly
    public function checkout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasActiveSubscription()) {
            return response()->json(['success' => false, 'message' => 'You already have an active subscription.'], 422);
        }

        $data = $request->validate([
            'callback_url' => ['required', 'url'],
        ]);

        $result = $this->paystack->initializeSubscription($user->email, $data['callback_url']);

        if (!$result) {
            return response()->json(['success' => false, 'message' => 'Could not initialize payment. Please try again.'], 502);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'payment_url' => $result['authorization_url'],
                'reference'   => $result['reference'],
                'access_code' => $result['access_code'],
            ],
        ]);
    }

    // POST /api/v1/patient/subscription/token
    // App calls this, gets a short-lived token, then opens statrahealth.com/subscribe?token=xxx
    public function generateToken(Request $request): JsonResponse
    {
        $user  = $request->user();
        $token = Str::uuid()->toString();

        Cache::put("sub_token:{$token}", $user->id, now()->addMinutes(15));

        $subscribeUrl = rtrim(config('app.frontend_url', 'https://statrahealth.com'), '/') . '/subscribe?token=' . $token;

        return response()->json([
            'success' => true,
            'data'    => [
                'token'         => $token,
                'subscribe_url' => $subscribeUrl,
                'expires_in'    => 900, // 15 minutes in seconds
            ],
        ]);
    }
}
