<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Daily Ticket Summary</title></head>
<body style="margin:0;background:#f5f8fc;font-family:Arial,sans-serif;color:#111827;">
<div style="max-width:680px;margin:24px auto;background:white;border-radius:14px;overflow:hidden">
  <div style="padding:28px;background:#161943;color:#ffffff">
    <strong style="font-size:20px">eLive Events</strong>
    <p style="font-size:12px;letter-spacing:1px;color:#cbd5e1">PLATFORM DAILY SALES REPORT</p>
    <h1 style="margin:0;font-size:26px">Daily Ticket Summary</h1>
    <p>{{ $reportDate }} · Tanzania time · All events</p>
  </div>
  <div style="padding:28px">
    <p>Hello Administrator,</p>
    <p>Here is the daily overview of confirmed ticket sales across the eLive Events platform.</p>
    <table width="100%" cellpadding="10" style="background:#f3f7fc;border-radius:8px">
      <tr><td>Tickets sold</td><td align="right"><strong>{{ number_format($totals['tickets']) }}</strong></td></tr>
      <tr><td>Confirmed orders</td><td align="right"><strong>{{ number_format($totals['orders']) }}</strong></td></tr>
      <tr><td>Free tickets</td><td align="right"><strong>{{ number_format($totals['free_tickets']) }}</strong></td></tr>
      <tr><td>Gross sales</td><td align="right"><strong>TZS {{ number_format($totals['gross'],2) }}</strong></td></tr>
      <tr><td>Recorded organizer net</td><td align="right"><strong>TZS {{ number_format($totals['organizer_net'],2) }}</strong></td></tr>
    </table>
    <h2 style="font-size:18px">Sales by event</h2>
    <table width="100%" cellpadding="8" border="1" style="border-collapse:collapse;border-color:#e5e7eb;font-size:14px">
      <thead><tr><th align="left">Event</th><th>Orders</th><th>Tickets</th><th>Free</th><th>Gross (TZS)</th><th>Net (TZS)</th></tr></thead>
      <tbody>
      @forelse ($groups as $group)
        <tr><td>{{ $group['event'] }}</td><td align="right">{{ $group['orders'] }}</td><td align="right">{{ $group['tickets'] }}</td><td align="right">{{ $group['free_tickets'] }}</td><td align="right">{{ number_format($group['gross'],2) }}</td><td align="right">{{ number_format($group['organizer_net'],2) }}</td></tr>
      @empty
        <tr><td colspan="6">No confirmed ticket sales for this period.</td></tr>
      @endforelse
      </tbody>
    </table>
    <p style="font-size:12px;color:#64748b">Only orders currently marked paid are counted. Pending, refunded and partially refunded orders are excluded. These figures are not proof of provider settlement or available payout funds.</p>
  </div>
</div>
</body>
</html>
