<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daily Check-in Reminder</title>
  <style>
    body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .header { background: #1b1b2f; padding: 32px 40px; text-align: center; }
    .header h1 { margin: 0; color: #ffffff; font-size: 22px; letter-spacing: .5px; }
    .header span { color: #7c6af7; font-weight: 700; }
    .body { padding: 36px 40px; color: #374151; }
    .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.65; }
    .cta { text-align: center; margin: 28px 0; }
    .cta a { background: #7c6af7; color: #fff; text-decoration: none; padding: 13px 32px; border-radius: 8px; font-size: 15px; font-weight: 600; display: inline-block; }
    .tip { background: #f0f0ff; border-left: 4px solid #7c6af7; border-radius: 4px; padding: 14px 18px; margin: 24px 0 0; font-size: 14px; color: #4b4b80; }
    .footer { text-align: center; padding: 20px 40px 28px; font-size: 12px; color: #9ca3af; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1><span>STATRA</span> — SCD Wellness</h1>
    </div>
    <div class="body">
      <p>Hi {{ $name }},</p>
      <p>This is your daily reminder to log how you're feeling today. Tracking consistently helps you and your care team spot patterns early and respond before symptoms escalate.</p>
      <p>It takes less than 2 minutes. How are you feeling right now?</p>
      <div class="cta">
        <a href="{{ config('app.url') }}">Log Today's Check-in</a>
      </div>
      <div class="tip">
        <strong>Tip:</strong> Even on good days, logging a "Stable" check-in gives your care team a useful baseline.
      </div>
    </div>
    <div class="footer">
      You're receiving this because daily reminders are enabled on your STATRA account.<br>
      &copy; {{ date('Y') }} SCD Wellness Team
    </div>
  </div>
</body>
</html>
