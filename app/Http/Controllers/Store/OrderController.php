<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\PlaceOrderRequest;
use App\Models\BandOrder;
use App\Services\KorapayService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    private const UNIT_PRICE = 149.00;
    private const ORIGINAL   = 199.00;

    public function __construct(private KorapayService $korapay) {}

    public function store(PlaceOrderRequest $request): JsonResponse
    {
        $data      = $request->validated();
        $qty       = $data['quantity'];
        $unitPrice = self::UNIT_PRICE;
        $subtotal  = round($unitPrice * $qty, 2);
        $discount  = round((self::ORIGINAL - $unitPrice) * $qty, 2);

        $order = BandOrder::create([
            'order_number'   => BandOrder::generateOrderNumber(),
            'first_name'     => $data['first_name'],
            'last_name'      => $data['last_name'],
            'email'          => $data['email'],
            'phone'          => $data['phone'],
            'street_address' => $data['street_address'] ?? null,
            'city'           => $data['city'] ?? null,
            'state'          => $data['state'] ?? null,
            'band_size'      => $data['band_size'],
            'quantity'       => $qty,
            'plan'           => $data['plan'],
            'unit_price'     => $unitPrice,
            'subtotal'       => $subtotal,
            'discount'       => $discount,
            'shipping'       => 0,
            'total'          => $subtotal,
            'status'         => 'pending',
            'payment_status' => 'pending',
        ]);

        $checkoutUrl = $this->korapay->initializeCheckout($order);

        if (!$checkoutUrl) {
            $order->delete();
            return response()->json([
                'success' => false,
                'message' => 'Could not initialize payment. Please try again.',
            ], 502);
        }

        $order->update(['korapay_checkout_url' => $checkoutUrl]);

        return response()->json([
            'success' => true,
            'data'    => [
                'order_number'  => $order->order_number,
                'checkout_url'  => $checkoutUrl,
                'total'         => $order->total,
                'currency'      => 'USD',
            ],
        ], 201);
    }

    public function track(string $orderNumber): JsonResponse
    {
        $order = BandOrder::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'order_number'   => $order->order_number,
                'status'         => $order->status,
                'payment_status' => $order->payment_status,
                'band_size'      => $order->band_size,
                'quantity'       => $order->quantity,
                'plan'           => $order->planLabel(),
                'total'          => $order->total,
                'currency'       => 'USD',
                'shipping' => [
                    'tracking_number' => $order->tracking_number,
                    'courier'         => $order->courier,
                    'shipped_at'      => $order->shipped_at,
                    'estimated_days'  => '5–8 business days',
                ],
                'placed_at' => $order->created_at,
            ],
        ]);
    }
}
