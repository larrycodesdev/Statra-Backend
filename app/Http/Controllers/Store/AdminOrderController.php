<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Mail\Store\OrderStatusMail;
use App\Models\BandOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminOrderController extends Controller
{
    private const VALID_STATUSES = [
        'processing', 'packed', 'dispatched', 'in_transit',
        'shipped', 'delivered', 'cancelled', 'delayed',
    ];

    // GET /admin/orders — list with status + issue filters + search
    public function index(Request $request): JsonResponse
    {
        $orders = BandOrder::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->issue,  fn ($q) => $q->where('issue', $request->issue))
            ->when($request->search, fn ($q) => $q->where(function ($q2) use ($request) {
                $q2->where('order_number', 'like', "%{$request->search}%")
                   ->orWhere('email', 'like', "%{$request->search}%")
                   ->orWhere('first_name', 'like', "%{$request->search}%")
                   ->orWhere('last_name', 'like', "%{$request->search}%");
            }))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json(['success' => true, 'data' => $orders]);
    }

    // GET /admin/stats — dashboard summary cards
    public function stats(): JsonResponse
    {
        $total     = BandOrder::count();
        $inTransit = BandOrder::whereIn('status', ['processing', 'packed', 'dispatched', 'in_transit', 'shipped'])->count();
        $delivered = BandOrder::where('status', 'delivered')->count();
        $issues    = BandOrder::whereNotNull('issue')->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_orders' => $total,
                'in_transit'   => $inTransit,
                'delivered'    => $delivered,
                'issues'       => $issues,
            ],
        ]);
    }

    // GET /admin/orders/{orderNumber}
    public function show(string $orderNumber): JsonResponse
    {
        $order = BandOrder::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $order]);
    }

    // PATCH /admin/orders/{orderNumber}/status
    public function updateStatus(Request $request, string $orderNumber): JsonResponse
    {
        $data = $request->validate([
            'status'          => ['required', 'in:' . implode(',', self::VALID_STATUSES)],
            'issue'           => ['nullable', 'in:damaged,lost'],
            'delay_note'      => ['nullable', 'string', 'max:500'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'courier'         => ['nullable', 'string', 'max:100'],
            'notify'          => ['nullable', 'boolean'],
        ]);

        $order = BandOrder::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $updates = [
            'status'     => $data['status'],
            'issue'      => $data['issue'] ?? $order->issue,
            'delay_note' => $data['delay_note'] ?? null,
        ];

        if ($data['status'] === 'shipped' || isset($data['tracking_number'])) {
            $updates['tracking_number'] = $data['tracking_number'] ?? $order->tracking_number;
            $updates['courier']         = $data['courier'] ?? $order->courier;
            $updates['shipped_at']      = $order->shipped_at ?? now();
        }

        // Append to status history timeline
        $history   = $order->status_history ?? [];
        $history[] = [
            'status' => $data['status'],
            'at'     => now()->toISOString(),
            'note'   => $data['delay_note'] ?? null,
        ];
        $updates['status_history'] = $history;

        $order->update($updates);
        $order->refresh();

        // Email the customer on every status change (skip only if notify=false)
        if ($request->input('notify', true)) {
            Mail::to($order->email)->queue(new OrderStatusMail($order));
        }

        return response()->json([
            'success' => true,
            'message' => "Order {$orderNumber} updated to '{$data['status']}'.",
            'data'    => $order,
        ]);
    }

    // PATCH /admin/orders/{orderNumber}/issue — flag damaged or lost separately
    public function updateIssue(Request $request, string $orderNumber): JsonResponse
    {
        $data = $request->validate([
            'issue' => ['nullable', 'in:damaged,lost'],
        ]);

        $order = BandOrder::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $order->update(['issue' => $data['issue']]);
        $order->refresh();

        return response()->json(['success' => true, 'data' => $order]);
    }

    // GET /admin/activity — recent order activity (ordered by last update)
    public function activity(Request $request): JsonResponse
    {
        $orders = BandOrder::query()
            ->when($request->search, fn ($q) => $q->where(function ($q2) use ($request) {
                $q2->where('order_number', 'like', "%{$request->search}%")
                   ->orWhere('first_name', 'like', "%{$request->search}%")
                   ->orWhere('last_name', 'like', "%{$request->search}%");
            }))
            ->orderByDesc('updated_at')
            ->select(['id', 'order_number', 'first_name', 'last_name', 'quantity', 'total', 'status', 'updated_at'])
            ->paginate($request->integer('per_page', 20));

        return response()->json(['success' => true, 'data' => $orders]);
    }
}
