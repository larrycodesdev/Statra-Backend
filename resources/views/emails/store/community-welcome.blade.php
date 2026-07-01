<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Welcome to the STATRA Community</title>
  <style>
    body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .header { background: #1b1b2f; padding: 32px 40px; text-align: center; }
    .header h1 { margin: 0; color: #ffffff; font-size: 22px; }
    .header h1 span { color: #7c6af7; font-weight: 700; }
    .header p { margin: 8px 0 0; color: #a5b4fc; font-size: 14px; }
    .body { padding: 32px 40px; color: #374151; }
    .body > p { margin: 0 0 20px; font-size: 15px; line-height: 1.65; }
    .cta { text-align: center; margin: 28px 0 8px; }
    .cta a { background: #7c6af7; color: #fff; text-decoration: none; padding: 14px 36px; border-radius: 8px; font-size: 16px; font-weight: 600; display: inline-block; }
    .footer { text-align: center; padding: 20px 40px 28px; font-size: 12px; color: #9ca3af; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1><span>STATRA</span> Community</h1>
      <p>You're in!</p>
    </div>
    <div class="body">
      <p>Hi {{ $name }},</p>
      <p>Welcome to the STATRA community! We're thrilled to have you with us.</p>
      <p>Click the button below to join our private Telegram group where you'll get early access, updates, and connect with other STATRA members.</p>

      <div class="cta">
        <a href="{{ env('COMMUNITY_TELEGRAM_URL', '#') }}">Join the Telegram Group</a>
      </div>

      <p style="font-size:13px; color:#6b7280; margin-top:24px;">
        If the button doesn't work, copy and paste this link into your browser:<br>
        <a href="{{ env('COMMUNITY_TELEGRAM_URL', '#') }}" style="color:#7c6af7;">{{ env('COMMUNITY_TELEGRAM_URL', 'Link coming soon') }}</a>
      </p>
    </div>
    <div class="footer">
      <p>&copy; {{ date('Y') }} Statra Health. All rights reserved.</p>
    </div>
  </div>
</body>
</html>
