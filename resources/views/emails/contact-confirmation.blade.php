<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>We received your message</title>
<style>
  body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
  .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
  .header { background: #1b1b2f; padding: 32px 40px; text-align: center; }
  .header h1 { margin: 0; color: #ffffff; font-size: 22px; letter-spacing: .5px; }
  .header h1 span { color: #7c6af7; font-weight: 700; }
  .header p { margin: 6px 0 0; color: #9ca3af; font-size: 13px; }
  .icon { width: 52px; height: 52px; background: #7c6af7; border-radius: 50%; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; }
  .body { padding: 36px 40px; color: #374151; }
  .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.65; }
  .recap { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px 20px; margin: 24px 0; }
  .recap-label { font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #9ca3af; margin-bottom: 6px; }
  .recap-text { font-size: 14px; color: #374151; line-height: 1.65; white-space: pre-wrap; }
  .footer { text-align: center; padding: 20px 40px 28px; font-size: 12px; color: #9ca3af; line-height: 1.7; }
  .footer a { color: #7c6af7; text-decoration: none; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <div class="icon">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <h1><span>STATRA</span> Health</h1>
    <p>We got your message</p>
  </div>
  <div class="body">
    <p>Hi {{ explode(' ', $contact->full_name)[0] }},</p>
    <p>Thanks for reaching out. Our team has received your message and will get back to you within 1–2 business days.</p>

    <div class="recap">
      <div class="recap-label">Your message</div>
      <div class="recap-text">{{ $contact->message }}</div>
    </div>

    <p style="font-size:13px;color:#6b7280;">If you have anything to add, just reply to this email.</p>
  </div>
  <div class="footer">
    &copy; {{ date('Y') }} Statra Health &mdash; <a href="https://statrahealth.com">statrahealth.com</a>
  </div>
</div>
</body>
</html>
