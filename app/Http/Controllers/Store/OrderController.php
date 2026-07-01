<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\PlaceOrderRequest;
use App\Models\BandOrder;
use App\Services\KorapayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    private const UNIT_PRICE = 149.00;
    private const ORIGINAL   = 199.00;

    // Human-readable labels for each status step in the tracking timeline
    private const STATUS_LABELS = [
        'pending'    => 'Order placed',
        'paid'       => 'Payment confirmed',
        'processing' => 'Processing',
        'packed'     => 'Packed',
        'dispatched' => 'Dispatched',
        'in_transit' => 'In Transit',
        'shipped'    => 'Shipped',
        'delivered'  => 'Delivered',
        'delayed'    => 'Delayed',
        'cancelled'  => 'Cancelled',
    ];

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
            'country'        => $data['country'] ?? null,
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
            // Seed the first status history entry
            'status_history' => [[
                'status' => 'pending',
                'at'     => now()->toISOString(),
                'note'   => null,
            ]],
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
                'order_number' => $order->order_number,
                'checkout_url' => $checkoutUrl,
                'total'        => $order->total,
                'currency'     => 'USD',
            ],
        ], 201);
    }

    // GET /orders/{orderNumber} — public tracking endpoint
    public function track(string $orderNumber): JsonResponse
    {
        $order = BandOrder::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        // Build timeline from stored history, or fall back to minimal history
        $rawHistory = $order->status_history ?? [];
        if (empty($rawHistory)) {
            $rawHistory = [['status' => 'pending', 'at' => $order->created_at->toISOString(), 'note' => null]];
        }

        $timeline = array_map(fn ($entry) => [
            'status' => $entry['status'],
            'label'  => self::STATUS_LABELS[$entry['status']] ?? ucfirst($entry['status']),
            'date'   => $entry['at'],
            'note'   => $entry['note'] ?? null,
        ], $rawHistory);

        $isDelayed = $order->status === 'delayed' || $order->issue === 'delayed';

        return response()->json([
            'success' => true,
            'data'    => [
                'order_number'  => $order->order_number,
                'status'        => $order->status,
                'issue'         => $order->issue,
                'delay_note'    => $order->delay_note,
                'is_delayed'    => $isDelayed,
                'timeline'      => $timeline,
                'order_info'    => [
                    'placed_at'        => $order->created_at,
                    'estimated_dropoff' => $order->shipped_at
                        ? $order->shipped_at->addDays(8)->toDateString()
                        : null,
                ],
                'customer'  => [
                    'full_name' => trim("{$order->first_name} {$order->last_name}"),
                    'email'     => $order->email,
                    'phone'     => $order->phone,
                ],
                'location'  => [
                    'street_address' => $order->street_address,
                    'city'           => $order->city,
                    'state'          => $order->state,
                    'country'        => $order->country,
                ],
                'delivery'  => [
                    'tracking_number' => $order->tracking_number,
                    'courier'         => $order->courier,
                    'shipped_at'      => $order->shipped_at,
                    'delivery_fee'    => $order->shipping == 0 ? 'Free' : '$' . number_format($order->shipping, 2),
                ],
                'items' => [
                    [
                        'name'     => 'Statra Band',
                        'quantity' => $order->quantity,
                        'price'    => $order->unit_price,
                        'total'    => $order->subtotal,
                    ],
                ],
                'pricing' => [
                    'subtotal'  => $order->subtotal,
                    'discount'  => $order->discount,
                    'shipping'  => $order->shipping,
                    'total'     => $order->total,
                    'currency'  => 'USD',
                ],
                'rating' => $order->rating,
            ],
        ]);
    }

    // POST /orders/{orderNumber}/review — submit star rating + review after delivery
    public function review(Request $request, string $orderNumber): JsonResponse
    {
        $order = BandOrder::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($order->status !== 'delivered') {
            return response()->json(['success' => false, 'message' => 'Reviews can only be submitted after delivery.'], 422);
        }

        if ($order->rating !== null) {
            return response()->json(['success' => false, 'message' => 'You have already reviewed this order.'], 422);
        }

        $data = $request->validate([
            'rating'      => ['required', 'integer', 'min:1', 'max:5'],
            'review_text' => ['nullable', 'string', 'max:1000'],
        ]);

        $order->update($data);

        return response()->json(['success' => true, 'message' => 'Thank you for your review!']);
    }
}
