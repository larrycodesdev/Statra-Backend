<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\UpdateOrderStatusRequest;
use App\Mail\Store\OrderShippedMail;
use App\Models\BandOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = BandOrder::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where(function ($q2) use ($request) {
                $q2->where('order_number', 'like', "%{$request->search}%")
                   ->orWhere('email', 'like', "%{$request->search}%")
                   ->orWhere('last_name', 'like', "%{$request->search}%");
            }))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }

    public function show(string $orderNumber): JsonResponse
    {
        $order = BandOrder::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $order]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, string $orderNumber): JsonResponse
    {
        $order = BandOrder::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $updates = ['status' => $request->status];

        if ($request->status === 'shipped') {
            $updates['tracking_number'] = $request->tracking_number;
            $updates['courier']         = $request->courier;
            $updates['shipped_at']      = now();

            Mail::to($order->email)->queue(new OrderShippedMail($order, $request->tracking_number, $request->courier));
        }

        $order->update($updates);

        return response()->json([
            'success' => true,
            'message' => "Order {$orderNumber} updated to '{$request->status}'.",
            'data'    => $order->fresh(),
        ]);
    }
}
