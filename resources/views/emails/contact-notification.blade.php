<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Contact Message</title>
<style>
  body { margin: 0; padding: 0; background: #f4f6f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
  .wrap { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
  .header { background: #0D1117; padding: 28px 36px; }
  .header img { height: 28px; }
  .header-label { color: #00C8A0; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; margin-top: 6px; }
  .body { padding: 32px 36px; }
  h1 { font-size: 20px; font-weight: 700; color: #0D1117; margin: 0 0 6px; }
  .subtitle { font-size: 13px; color: #6B7280; margin: 0 0 28px; }
  .field { margin-bottom: 18px; }
  .field-label { font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #9CA3AF; margin-bottom: 4px; }
  .field-value { font-size: 15px; color: #111827; font-weight: 500; }
  .message-box { background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 6px; padding: 16px; margin-top: 4px; font-size: 14px; color: #374151; line-height: 1.7; white-space: pre-wrap; }
  .divider { height: 1px; background: #F3F4F6; margin: 24px 0; }
  .footer { padding: 20px 36px; background: #F9FAFB; border-top: 1px solid #F3F4F6; }
  .footer p { font-size: 12px; color: #9CA3AF; margin: 0; }
  .reply-hint { background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 6px; padding: 12px 16px; margin-top: 20px; font-size: 13px; color: #065F46; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <div style="font-size:18px;font-weight:800;color:#ffffff;letter-spacing:-0.3px;">STATRA</div>
    <div class="header-label">New Contact Request</div>
  </div>
  <div class="body">
    <h1>New message from {{ $contact->full_name }}</h1>
    <p class="subtitle">Received {{ now()->format('F j, Y \a\t g:i A') }}</p>

    <div class="field">
      <div class="field-label">Full Name</div>
      <div class="field-value">{{ $contact->full_name }}</div>
    </div>

    <div class="field">
      <div class="field-label">Email Address</div>
      <div class="field-value">{{ $contact->email }}</div>
    </div>

    @if($contact->phone)
    <div class="field">
      <div class="field-label">Phone Number</div>
      <div class="field-value">{{ $contact->phone }}</div>
    </div>
    @endif

    <div class="divider"></div>

    <div class="field">
      <div class="field-label">Message</div>
      <div class="message-box">{{ $contact->message }}</div>
    </div>

    <div class="reply-hint">
      💬 Reply directly to this email to respond to {{ $contact->full_name }} — it will go straight to {{ $contact->email }}.
    </div>
  </div>
  <div class="footer">
    <p>This message was submitted via the contact form on statra.health.</p>
  </div>
</div>
</body>
</html>
