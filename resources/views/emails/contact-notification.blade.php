<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Contact Message</title>
<style>
  body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
  .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
  .header { background: #1b1b2f; padding: 24px 36px; }
  .header h1 { margin: 0; font-size: 18px; font-weight: 800; color: #ffffff; letter-spacing: -.2px; }
  .header h1 span { color: #7c6af7; }
  .header-label { color: #a5b4fc; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; margin-top: 4px; }
  .body { padding: 32px 36px; }
  h2 { font-size: 18px; font-weight: 700; color: #1b1b2f; margin: 0 0 4px; }
  .subtitle { font-size: 13px; color: #6b7280; margin: 0 0 24px; }
  .field { margin-bottom: 16px; }
  .field-label { font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #9ca3af; margin-bottom: 3px; }
  .field-value { font-size: 15px; color: #111827; font-weight: 500; }
  .divider { height: 1px; background: #f3f4f6; margin: 20px 0; }
  .message-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; font-size: 14px; color: #374151; line-height: 1.7; white-space: pre-wrap; }
  .reply-hint { background: #f0f0ff; border: 1px solid #c4b5fd; border-radius: 8px; padding: 12px 16px; margin-top: 20px; font-size: 13px; color: #4b4b80; }
  .footer { padding: 16px 36px 24px; text-align: center; font-size: 12px; color: #9ca3af; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1><span>STATRA</span> Health</h1>
    <div class="header-label">New Contact Request</div>
  </div>
  <div class="body">
    <h2>New message from {{ $contact->full_name }}</h2>
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
      Reply directly to this email to respond &mdash; it goes straight to {{ $contact->email }}.
    </div>
  </div>
  <div class="footer">
    Submitted via the contact form on statrahealth.com
  </div>
</div>
</body>
</html>
