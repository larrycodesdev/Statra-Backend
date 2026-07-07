<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>We received your message</title>
<style>
  body { margin: 0; padding: 0; background: #f4f6f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
  .wrap { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
  .header { background: #0D1117; padding: 32px 36px; text-align: center; }
  .checkmark { width: 52px; height: 52px; background: #00C8A0; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px; }
  .body { padding: 36px 36px 28px; text-align: center; }
  h1 { font-size: 22px; font-weight: 700; color: #0D1117; margin: 0 0 10px; }
  .lead { font-size: 15px; color: #4B5563; line-height: 1.7; margin: 0 0 28px; }
  .message-recap { background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 6px; padding: 16px 20px; text-align: left; margin-bottom: 28px; }
  .recap-label { font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #9CA3AF; margin-bottom: 6px; }
  .recap-text { font-size: 13px; color: #374151; line-height: 1.6; white-space: pre-wrap; }
  .divider { height: 1px; background: #F3F4F6; margin: 0; }
  .footer { padding: 20px 36px; text-align: center; }
  .footer p { font-size: 12px; color: #9CA3AF; margin: 0 0 4px; }
  .footer a { color: #00C8A0; text-decoration: none; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <div>
      <div style="width:52px;height:52px;background:#00C8A0;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:14px;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0D1117" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
    </div>
    <div style="font-size:18px;font-weight:800;color:#ffffff;letter-spacing:-0.3px;">STATRA</div>
  </div>
  <div class="body">
    <h1>We got your message, {{ explode(' ', $contact->full_name)[0] }}!</h1>
    <p class="lead">
      Thank you for reaching out. Our team has received your message and will get back to you as soon as possible — usually within 1–2 business days.
    </p>
    <div class="message-recap">
      <div class="recap-label">Your message</div>
      <div class="recap-text">{{ $contact->message }}</div>
    </div>
    <p style="font-size:13px;color:#6B7280;">
      If you have anything to add, just reply to this email.
    </p>
  </div>
  <div class="divider"></div>
  <div class="footer">
    <p>© {{ date('Y') }} Statra Health. All rights reserved.</p>
    <p><a href="https://statra.health">statra.health</a></p>
  </div>
</div>
</body>
</html>
