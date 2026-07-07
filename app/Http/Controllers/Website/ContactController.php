<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Mail\ContactConfirmationMail;
use App\Mail\ContactNotificationMail;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'email'     => ['required', 'email', 'max:255'],
            'message'   => ['required', 'string', 'max:5000'],
        ]);

        $contact = ContactMessage::create($data);

        Mail::to('hello@statra.health')->queue(new ContactNotificationMail($contact));
        Mail::to($contact->email)->queue(new ContactConfirmationMail($contact));

        return ApiResponse::success(null, 'Message received. We will get back to you shortly.');
    }
}
