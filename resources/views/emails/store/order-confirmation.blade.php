<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Confirmed</title>
  <style>
    body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .header { background: #1b1b2f; padding: 32px 40px; text-align: center; }
    .header h1 { margin: 0; color: #ffffff; font-size: 22px; letter-spacing: .5px; }
    .header span { color: #7c6af7; font-weight: 700; }
    .check { width: 52px; height: 52px; background: #7c6af7; border-radius: 50%; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; font-size: 26px; line-height: 52px; }
    .header p { margin: 8px 0 0; color: #a5b4fc; font-size: 14px; }
    .body { padding: 32px 40px; color: #374151; }
    .body > p { margin: 0 0 20px; font-size: 15px; line-height: 1.65; }
    .order-box { background: #f9fafb; border-radius: 10px; padding: 20px 24px; margin-bottom: 24px; }
    .order-number { font-size: 18px; font-weight: 700; color: #7c6af7; margin-bottom: 16px; }
    .row { display: flex; justify-content: space-between; font-size: 14px; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
    .row:last-child { border-bottom: none; font-weight: 600; font-size: 15px; color: #1b1b2f; }
    .row span:first-child { color: #6b7280; }
    .shipping-note { background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #166534; margin-bottom: 24px; }
    .cta { text-align: center; margin: 8px 0 4px; }
    .cta a { background: #7c6af7; color: #fff; text-decoration: none; padding: 13px 32px; border-radius: 8px; font-size: 15px; font-weight: 600; display: inline-block; }
    .footer { text-align: center; padding: 20px 40px 28px; font-size: 12px; color: #9ca3af; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <div class="check">✓</div>
      <h1><span>STATRA</span> Band</h1>
      <p>Order confirmed — your band is on its way!</p>
    </div>
    <div class="body">
      <p>Hi {{ $order->first_name }},</p>
      <p>Thank you for your order. We've received your payment and your STATRA Band is being prepared for shipment. You'll receive a tracking link within 24 hours once it ships.</p>

      <div class="order-box">
        <div class="order-number">#{{ $order->order_number }}</div>
        <div class="row"><span>Product</span><span>STATRA Band (Size {{ $order->band_size }})</span></div>
        <div class="row"><span>Plan</span><span>{{ $order->planLabel() }}</span></div>
        <div class="row"><span>Quantity</span><span>{{ $order->quantity }}</span></div>
        <div class="row"><span>Shipping</span><span>Standard (5–8 days) — Free</span></div>
        <div class="row"><span>Total charged</span><span>${{ number_format($order->total, 2) }}</span></div>
      </div>

      <div class="shipping-note">
        📦 Estimated delivery: <strong>5–8 business days</strong>. Free shipping worldwide.
      </div>

      <div class="cta">
        <a href="{{ config('app.frontend_store_url') }}/order-confirmed?ref={{ $order->order_number }}">View Order Status</a>
      </div>
    </div>
    <div class="footer">
      Questions? Reply to this email or contact us at support@scdwellness.app<br>
      &copy; {{ date('Y') }} STATRA Health Platform
    </div>
  </div>
</body>
</html>
