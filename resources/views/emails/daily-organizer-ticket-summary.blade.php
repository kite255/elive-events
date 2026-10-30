<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Your Daily Ticket Sales</title></head>
<body style="margin:0;background:#f5f8fc;color:#111827;font-family:Arial,sans-serif;">
<div style="max-width:640px;margin:24px auto;background:#ffffff;border-radius:14px;overflow:hidden;">
  <div style="padding:28px;background:#007ab2;color:white">
    <strong style="font-size:20px">eLive Events</strong>
    <p style="font-size:12px;letter-spacing:1px">EVENT PERFORMANCE REPORT</p>
    <h1 style="margin:0;font-size:26px">Your Daily Ticket Sales</h1>
    <p>{{ $date }} · Tanzania time</p>
  </div>
  <div style="padding:28px">
    <p>Hello Organizer,</p>
    <p>Here is your confirmed ticket sales report for <strong>{{ $event->name }}</strong>.</p>
    <table width="100%" cellpadding="10" style="background:#f3f7fc;border-radius:8px">
      <tr><td>Tickets sold</td><td align="right"><strong>{{ number_format($totals['tickets']) }}</strong></td></tr>
      <tr><td>Confirmed orders</td><td align="right"><strong>{{ number_format($totals['orders']) }}</strong></td></tr>
      <tr><td>Free tickets</td><td align="right"><strong>{{ number_format($totals['free_tickets']) }}</strong></td></tr>
      <tr><td>Gross sales</td><td align="right"><strong>TZS {{ number_format($totals['gross'],2) }}</strong></td></tr>
      <tr><td>Organizer net</td><td align="right"><strong>TZS {{ number_format($totals['organizer_net'],2) }}</strong></td></tr>
    </table>
    <h2 style="font-size:18px">Ticket category breakdown</h2>
    <table width="100%" cellpadding="8" border="1" style="border-collapse:collapse;border-color:#e5e7eb;font-size:14px">
      <thead><tr><th align="left">Category</th><th>Tickets</th><th>Gross (TZS)</th></tr></thead>
      <tbody>
      @foreach ($categories as $category)
       <tr><td>{{ $category['name'] }}</td><td align="right">{{ $category['tickets'] }}</td><td align="right">{{ number_format($category['gross'],2) }}</td></tr>
      @endforeach
      </tbody>
    </table>
    <p style="font-size:12px;color:#64748b">Only confirmed paid orders are included. Refunded and partially refunded orders are excluded. This is not confirmation of provider settlement or money available for payout.</p>
  </div>
</div>
</body>
</html>
