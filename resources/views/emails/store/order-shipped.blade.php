<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Order Has Shipped</title>
  <style>
    body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .header { background: #1b1b2f; padding: 32px 40px; text-align: center; }
    .header h1 { margin: 0; color: #ffffff; font-size: 22px; letter-spacing: .5px; }
    .header span { color: #7c6af7; font-weight: 700; }
    .header p { margin: 8px 0 0; color: #a5b4fc; font-size: 14px; }
    .body { padding: 32px 40px; color: #374151; }
    .body > p { margin: 0 0 20px; font-size: 15px; line-height: 1.65; }
    .tracking-box { background: #f0f0ff; border: 1px solid #c4b5fd; border-radius: 10px; padding: 20px 24px; margin-bottom: 24px; text-align: center; }
    .tracking-box .label { font-size: 12px; text-transform: uppercase; letter-spacing: .6px; color: #6b7280; margin-bottom: 6px; }
    .tracking-box .number { font-size: 22px; font-weight: 700; color: #7c6af7; letter-spacing: 1px; }
    .tracking-box .courier { font-size: 13px; color: #6b7280; margin-top: 6px; }
    .order-box { background: #f9fafb; border-radius: 10px; padding: 20px 24px; margin-bottom: 24px; }
    .row { display: flex; justify-content: space-between; font-size: 14px; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
    .row:last-child { border-bottom: none; font-weight: 600; font-size: 15px; color: #1b1b2f; }
    .row span:first-child { color: #6b7280; }
    .cta { text-align: center; margin: 8px 0 4px; }
    .cta a { background: #7c6af7; color: #fff; text-decoration: none; padding: 13px 32px; border-radius: 8px; font-size: 15px; font-weight: 600; display: inline-block; }
    .footer { text-align: center; padding: 20px 40px 28px; font-size: 12px; color: #9ca3af; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1><span>STATRA</span> Band</h1>
      <p>Your band is on its way!</p>
    </div>
    <div class="body">
      <p>Hi {{ $order->first_name }},</p>
      <p>Great news — your STATRA Band has been shipped! Use your tracking number below to follow its journey.</p>

      <div class="tracking-box">
        <div class="label">Tracking Number</div>
        <div class="number">{{ $trackingNumber }}</div>
        <div class="courier">via {{ $courier }}</div>
      </div>

      <div class="order-box">
        <div class="row"><span>Order</span><span>#{{ $order->order_number }}</span></div>
        <div class="row"><span>Product</span><span>STATRA Band (Size {{ $order->band_size }})</span></div>
        <div class="row"><span>Plan</span><span>{{ $order->planLabel() }}</span></div>
        <div class="row"><span>Shipped</span><span>{{ $order->shipped_at->format('M j, Y') }}</span></div>
        <div class="row"><span>Est. delivery</span><span>5–8 business days</span></div>
      </div>

      <div class="cta">
        <a href="{{ config('app.frontend_store_url') }}/order-confirmed?ref={{ $order->order_number }}">Track My Order</a>
      </div>
    </div>
    <div class="footer">
      Questions? Reply to this email or contact us at support@scdwellness.app<br>
      &copy; {{ date('Y') }} STATRA Health Platform
    </div>
  </div>
</body>
</html>
