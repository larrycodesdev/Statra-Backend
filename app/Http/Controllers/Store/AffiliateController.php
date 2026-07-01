<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Mail\Store\AffiliateWelcomeMail;
use App\Models\Affiliate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AffiliateController extends Controller
{
    public function join(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'max:255'],
            'country'         => ['nullable', 'string', 'max:100'],
            'owns_band'       => ['required', 'boolean'],
            'social_platform' => ['nullable', 'string', 'in:tiktok,youtube,twitter,instagram,snapchat,facebook,linkedin'],
        ]);

        if (Affiliate::where('email', $data['email'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You have already applied to the affiliate program.',
            ], 422);
        }

        Affiliate::create($data);

        Mail::to($data['email'])->queue(new AffiliateWelcomeMail($data['name']));

        return response()->json([
            'success' => true,
            'message' => 'Welcome! A Telegram link has been sent to your email.',
        ]);
    }
}
