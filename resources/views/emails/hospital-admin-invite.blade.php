<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>You've been added as a Hospital Admin</title>
  <style>
    body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .header { background: #1b1b2f; padding: 32px 40px; text-align: center; }
    .header h1 { margin: 0; color: #ffffff; font-size: 22px; letter-spacing: .5px; }
    .header h1 span { color: #7c6af7; font-weight: 700; }
    .header p { margin: 6px 0 0; color: #9ca3af; font-size: 13px; }
    .body { padding: 36px 40px; color: #374151; }
    .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.65; }
    .hospital-badge { background: #f0f0ff; border: 1px solid #c4b5fd; border-radius: 8px; padding: 14px 20px; margin: 20px 0; display: flex; align-items: center; gap: 12px; }
    .hospital-badge .icon { font-size: 22px; line-height: 1; }
    .hospital-badge .name { font-size: 16px; font-weight: 700; color: #1b1b2f; }
    .hospital-badge .role { font-size: 12px; color: #6b7280; margin-top: 2px; }
    .cta { text-align: center; margin: 28px 0; }
    .cta a { background: #7c6af7; color: #fff; text-decoration: none; padding: 14px 36px; border-radius: 8px; font-size: 15px; font-weight: 600; display: inline-block; }
    .link-fallback { font-size: 12px; color: #9ca3af; text-align: center; margin-top: 12px; word-break: break-all; }
    .link-fallback a { color: #7c6af7; }
    .expiry-note { text-align: center; font-size: 13px; color: #9ca3af; margin-top: 4px; }
    .expiry-note strong { color: #ef4444; }
    .divider { border: none; border-top: 1px solid #f3f4f6; margin: 24px 0; }
    .ignore-note { font-size: 13px; color: #9ca3af; }
    .footer { text-align: center; padding: 20px 40px 28px; font-size: 12px; color: #9ca3af; line-height: 1.7; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1><span>STATRA</span> Health</h1>
      <p>Hospital Portal &mdash; Admin Invitation</p>
    </div>
    <div class="body">
      <p>Hi {{ $name }},</p>
      <p>You've been added as an administrator for the following hospital on the Statra Health platform:</p>

      <div class="hospital-badge">
        <div class="icon">🏥</div>
        <div>
          <div class="name">{{ $hospitalName }}</div>
          <div class="role">Hospital Administrator</div>
        </div>
      </div>

      <p>Click the button below to set your password and access the hospital dashboard where you can manage doctors, staff, and patients.</p>

      <div class="cta">
        <a href="{{ $inviteUrl }}">Set Password &amp; Get Started</a>
      </div>
      <div class="link-fallback">
        Button not working? Copy this link into your browser:<br>
        <a href="{{ $inviteUrl }}">{{ $inviteUrl }}</a>
      </div>
      <div class="expiry-note">This link expires in <strong>72 hours</strong>.</div>

      <hr class="divider">

      <p class="ignore-note">If you weren't expecting this invitation, you can safely ignore this email. No action is required.</p>
    </div>
    <div class="footer">
      &copy; {{ date('Y') }} Statra Health &mdash; statrahealth.com
    </div>
  </div>
</body>
</html>
