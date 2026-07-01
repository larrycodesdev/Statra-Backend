<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Update</title>
  <style>
    body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .header { background: #1b1b2f; padding: 32px 40px; text-align: center; }
    .header h1 { margin: 0; color: #ffffff; font-size: 22px; }
    .header h1 span { color: #7c6af7; font-weight: 700; }
    .header p { margin: 8px 0 0; color: #a5b4fc; font-size: 14px; }
    .body { padding: 32px 40px; color: #374151; }
    .body > p { margin: 0 0 20px; font-size: 15px; line-height: 1.65; }
    .status-badge { display: inline-block; padding: 6px 18px; border-radius: 20px; font-size: 14px; font-weight: 600; text-transform: capitalize; margin-bottom: 24px; }
    .status-processing, .status-packed, .status-dispatched, .status-in_transit { background: #eff6ff; color: #2563eb; }
    .status-shipped { background: #f0f0ff; color: #7c6af7; }
    .status-delivered { background: #ecfdf5; color: #059669; }
    .status-delayed, .status-cancelled { background: #fff1f2; color: #e11d48; }
    .order-box { background: #f9fafb; border-radius: 10px; padding: 20px 24px; margin-bottom: 24px; }
    .row { display: flex; justify-content: space-between; font-size: 14px; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
    .row:last-child { border-bottom: none; font-weight: 600; color: #1b1b2f; }
    .row span:first-child { color: #6b7280; }
    .note-box { background: #fff7ed; border: 1px solid #fdba74; border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; font-size: 14px; color: #92400e; }
    .cta { text-align: center; margin: 8px 0 4px; }
    .cta a { background: #7c6af7; color: #fff; text-decoration: none; padding: 13px 32px; border-radius: 8px; font-size: 15px; font-weight: 600; display: inline-block; }
    .footer { text-align: center; padding: 20px 40px 28px; font-size: 12px; color: #9ca3af; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1><span>STATRA</span> Band</h1>
      <p>Order #{{ $order->order_number }}</p>
    </div>
    <div class="body">
      <p>Hi {{ $order->first_name }},</p>
      <p>There's an update on your order:</p>

      <span class="status-badge status-{{ $order->status }}">{{ str_replace('_', ' ', $order->status) }}</span>

      @if ($order->delay_note)
      <div class="note-box">
        <strong>Note:</strong> {{ $order->delay_note }}
      </div>
      @endif

      <div class="order-box">
        <div class="row"><span>Order Number</span><span>{{ $order->order_number }}</span></div>
        <div class="row"><span>Item</span><span>Statra Band × {{ $order->quantity }}</span></div>
        @if ($order->tracking_number)
        <div class="row"><span>Tracking</span><span>{{ $order->tracking_number }} ({{ $order->courier }})</span></div>
        @endif
        <div class="row"><span>Total</span><span>${{ number_format($order->total, 2) }}</span></div>
      </div>

      <div class="cta">
        <a href="{{ config('app.store_url', config('app.url')) }}/track/{{ $order->order_number }}">Track Your Order</a>
      </div>
    </div>
    <div class="footer">
      <p>Questions? Email us at <a href="mailto:{{ config('mail.from.address') }}" style="color:#7c6af7;">{{ config('mail.from.address') }}</a></p>
      <p style="margin-top:8px;">&copy; {{ date('Y') }} Statra Health. All rights reserved.</p>
    </div>
  </div>
</body>
</html>
