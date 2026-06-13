<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Weekly Health Summary</title>
  <style>
    body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    .wrapper { max-width: 580px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .header { background: #1b1b2f; padding: 32px 40px; text-align: center; }
    .header h1 { margin: 0; color: #ffffff; font-size: 22px; letter-spacing: .5px; }
    .header span { color: #7c6af7; font-weight: 700; }
    .header p { margin: 8px 0 0; color: #a5b4fc; font-size: 13px; }
    .body { padding: 32px 40px; color: #374151; }
    .body > p { margin: 0 0 20px; font-size: 15px; line-height: 1.65; }
    .stats-grid { display: table; width: 100%; border-collapse: separate; border-spacing: 10px; margin: 0 -10px 8px; }
    .stat { display: table-cell; background: #f9fafb; border-radius: 8px; padding: 16px; text-align: center; width: 25%; }
    .stat .value { font-size: 26px; font-weight: 700; color: #1b1b2f; line-height: 1; }
    .stat .label { font-size: 11px; color: #6b7280; margin-top: 4px; text-transform: uppercase; letter-spacing: .5px; }
    .section-title { font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: .6px; color: #9ca3af; margin: 24px 0 10px; }
    .status-bar { background: #f3f4f6; border-radius: 6px; overflow: hidden; height: 10px; margin-bottom: 6px; }
    .status-bar-fill { height: 10px; border-radius: 6px; }
    .status-row { display: flex; align-items: center; justify-content: space-between; font-size: 13px; margin-bottom: 10px; }
    .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 6px; flex-shrink: 0; }
    .red-flag-box { background: #fff1f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 14px 18px; margin-top: 16px; font-size: 14px; color: #b91c1c; }
    .no-data { text-align: center; padding: 32px; color: #9ca3af; font-size: 14px; }
    .cta { text-align: center; margin: 28px 0 8px; }
    .cta a { background: #7c6af7; color: #fff; text-decoration: none; padding: 13px 32px; border-radius: 8px; font-size: 15px; font-weight: 600; display: inline-block; }
    .footer { text-align: center; padding: 20px 40px 28px; font-size: 12px; color: #9ca3af; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1><span>STATRA</span> — SCD Wellness</h1>
      <p>Weekly Summary &bull; {{ $stats['week_range'] }}</p>
    </div>
    <div class="body">
      <p>Hi {{ $name }}, here's how your week looked.</p>

      @if($stats['total_checkins'] === 0)
        <div class="no-data">
          No check-ins recorded this week.<br>
          Start tracking today to see your trends here next week.
        </div>
      @else
        {{-- Key numbers --}}
        <div class="stats-grid">
          <div class="stat">
            <div class="value">{{ $stats['total_checkins'] }}</div>
            <div class="label">Check-ins</div>
          </div>
          <div class="stat">
            <div class="value">{{ $stats['avg_pain'] }}</div>
            <div class="label">Avg Pain</div>
          </div>
          <div class="stat">
            <div class="value">{{ $stats['red_flag_count'] }}</div>
            <div class="label">Red Flags</div>
          </div>
          <div class="stat">
            <div class="value">{{ $stats['days_logged'] }}/7</div>
            <div class="label">Days Logged</div>
          </div>
        </div>

        {{-- Status breakdown --}}
        <div class="section-title">Status Breakdown</div>
        @foreach ($stats['status_breakdown'] as $status => $count)
          @php
            $pct = $stats['total_checkins'] > 0 ? round(($count / $stats['total_checkins']) * 100) : 0;
            $colors = ['Stable' => '#22c55e', 'Watch closely' => '#f59e0b', 'Elevated' => '#f97316', 'Urgent' => '#ef4444'];
            $color = $colors[$status] ?? '#6b7280';
          @endphp
          <div class="status-row">
            <span><span class="status-dot" style="background:{{ $color }}"></span>{{ $status }}</span>
            <span style="color:#6b7280">{{ $count }} &nbsp;({{ $pct }}%)</span>
          </div>
          <div class="status-bar">
            <div class="status-bar-fill" style="width:{{ $pct }}%;background:{{ $color }}"></div>
          </div>
        @endforeach

        {{-- Most common status --}}
        <p style="font-size:14px;color:#6b7280;margin-top:14px;">
          Most common status this week: <strong style="color:#1b1b2f">{{ $stats['most_common_status'] }}</strong>
        </p>

        @if($stats['red_flag_count'] > 0)
          <div class="red-flag-box">
            &#9888; You had <strong>{{ $stats['red_flag_count'] }} red-flag event{{ $stats['red_flag_count'] > 1 ? 's' : '' }}</strong> this week. Please follow up with your care team if you haven't already.
          </div>
        @endif
      @endif

      <div class="cta">
        <a href="{{ config('app.url') }}">View Full History</a>
      </div>
    </div>
    <div class="footer">
      You're receiving this weekly summary because your STATRA account has email notifications enabled.<br>
      &copy; {{ date('Y') }} SCD Wellness Team
    </div>
  </div>
</body>
</html>
