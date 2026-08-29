<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\BandOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
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

    // GET /api/v1/patient/orders
    public function index(Request $request): JsonResponse
    {
        $orders = BandOrder::where('email', $request->user()->email)
            ->orderByDesc('created_at')
            ->get();

        $data = $orders->map(fn ($order) => $this->formatOrder($order));

        return response()->json(['success' => true, 'data' => $data]);
    }

    // GET /api/v1/patient/orders/{orderNumber}
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $order = BandOrder::where('order_number', $orderNumber)
            ->where('email', $request->user()->email)
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->formatOrder($order)]);
    }

    private function formatOrder(BandOrder $order): array
    {
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

        return [
            'order_number'     => $order->order_number,
            'status'           => $order->status,
            'payment_status'   => $order->payment_status,
            'issue'            => $order->issue,
            'delay_note'       => $order->delay_note,
            'timeline'         => $timeline,
            'items'            => [
                [
                    'name'     => 'Statra Band',
                    'quantity' => $order->quantity,
                    'size'     => $order->band_size,
                    'plan'     => $order->planLabel(),
                    'price'    => $order->unit_price,
                    'total'    => $order->subtotal,
                ],
            ],
            'pricing'          => [
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'shipping' => $order->shipping,
                'total'    => $order->total,
                'currency' => 'USD',
            ],
            'delivery'         => [
                'tracking_number'   => $order->tracking_number,
                'courier'           => $order->courier,
                'shipped_at'        => $order->shipped_at,
                'estimated_dropoff' => $order->shipped_at
                    ? $order->shipped_at->addDays(8)->toDateString()
                    : null,
            ],
            'placed_at'        => $order->created_at,
            'rating'           => $order->rating,
            'review_text'      => $order->review_text,
        ];
    }
}
