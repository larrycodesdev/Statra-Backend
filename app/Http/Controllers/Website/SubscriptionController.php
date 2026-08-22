<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SubscriptionController extends Controller
{
    public function __construct(private PaystackService $paystack) {}

    // POST /api/v1/website/subscription/init
    // Called by the frontend website after receiving the token in the URL
    public function init(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'        => ['required', 'string'],
            'callback_url' => ['required', 'url'],
        ]);

        $userId = Cache::pull("sub_token:{$data['token']}");

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token. Please go back to the app and try again.',
            ], 422);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        $result = $this->paystack->initializeSubscription($user->email, $data['callback_url']);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Could not initialize payment. Please try again.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'payment_url'  => $result['authorization_url'],
                'reference'    => $result['reference'],
                'access_code'  => $result['access_code'],
                'user_name'    => $user->full_name,
                'user_email'   => $user->email,
            ],
        ]);
    }
}
