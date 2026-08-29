<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Statra Verification Code</title>
  <style>
    body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .header { background: #1b1b2f; padding: 32px 40px; text-align: center; }
    .header h1 { margin: 0; color: #ffffff; font-size: 22px; letter-spacing: .5px; }
    .header h1 span { color: #7c6af7; font-weight: 700; }
    .header p { margin: 6px 0 0; color: #9ca3af; font-size: 13px; letter-spacing: .3px; }
    .body { padding: 36px 40px; color: #374151; }
    .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.65; }
    .otp-block { text-align: center; margin: 28px 0; }
    .otp-label { font-size: 12px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: #9ca3af; margin-bottom: 12px; }
    .otp-code { display: inline-block; background: #f0f0ff; border: 2px dashed #7c6af7; border-radius: 10px; padding: 16px 40px; font-size: 38px; font-weight: 700; letter-spacing: 10px; color: #1b1b2f; font-family: 'Courier New', Courier, monospace; }
    .expiry-note { text-align: center; font-size: 13px; color: #9ca3af; margin: 16px 0 0; }
    .expiry-note strong { color: #ef4444; }
    .divider { border: none; border-top: 1px solid #f3f4f6; margin: 28px 0; }
    .warning { background: #fff7ed; border-left: 4px solid #f59e0b; border-radius: 4px; padding: 12px 16px; font-size: 13px; color: #92400e; line-height: 1.6; }
    .footer { text-align: center; padding: 20px 40px 28px; font-size: 12px; color: #9ca3af; line-height: 1.7; }
  </style>
</head>
<body>
  <div class="wrapper">

    <div class="header">
      <h1><span>STATRA</span> Health</h1>
      <p>SCD Wellness Platform</p>
    </div>

    <div class="body">
      <p>Hi {{ $name }},</p>
      <p>Use the verification code below to complete your sign-in. This code is unique to you and valid for a limited time.</p>

      <div class="otp-block">
        <div class="otp-label">Your verification code</div>
        <div class="otp-code">{{ $otp }}</div>
        <div class="expiry-note">Expires in <strong>10 minutes</strong></div>
      </div>

      <hr class="divider">

      <div class="warning">
        <strong>Never share this code.</strong> Statra will never ask for your OTP via call, chat, or email. If you didn't request this code, you can safely ignore this email.
      </div>
    </div>

    <div class="footer">
      This email was sent to you because a sign-in was attempted on your Statra account.<br>
      &copy; {{ date('Y') }} Statra Health &mdash; SCD Wellness Team
    </div>

  </div>
</body>
</html>
