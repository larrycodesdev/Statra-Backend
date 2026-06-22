<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Mail\Store\OrderConfirmationMail;
use App\Models\BandOrder;
use App\Services\KorapayService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentController extends Controller
{
    public function __construct(private KorapayService $korapay) {}

    public function webhook(Request $request): Response
    {
        $rawPayload = $request->getContent();
        $signature  = $request->header('X-Korapay-Signature', '');

        if (!$this->korapay->verifyWebhookSignature($rawPayload, $signature)) {
            Log::warning('Korapay webhook signature mismatch');
            return response('Unauthorized', 401);
        }

        $payload = json_decode($rawPayload, true);
        $event   = $payload['event'] ?? '';

        if ($event !== 'charge.success') {
            return response('OK', 200);
        }

        $reference = $payload['data']['reference'] ?? null;
        $order     = BandOrder::where('order_number', $reference)
                              ->where('payment_status', 'pending')
                              ->first();

        if (!$order) {
            return response('OK', 200);
        }

        $order->update([
            'payment_status'   => 'paid',
            'status'           => 'paid',
            'payment_reference' => $payload['data']['payment_reference'] ?? $reference,
        ]);

        Mail::to($order->email)->queue(new OrderConfirmationMail($order));

        return response('OK', 200);
    }
}
