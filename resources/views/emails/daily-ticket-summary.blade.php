<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Daily Ticket Summary</title></head>
<body style="font-family:Arial,sans-serif;color:#1f2937;line-height:1.6;">
    <h2>eLive Events — Daily Ticket Summary</h2>
    <p><strong>Report date:</strong> {{ $reportDate }} (Africa/Dar_es_Salaam)</p>
    <p>Confirmed paid orders: <strong>{{ number_format($totals['orders']) }}</strong> |
       Tickets: <strong>{{ number_format($totals['tickets']) }}</strong> |
       Free tickets: <strong>{{ number_format($totals['free_tickets']) }}</strong></p>
    <p>Gross ticket sales: <strong>TZS {{ number_format($totals['gross'], 2) }}</strong><br>
       Recorded organizer net: <strong>TZS {{ number_format($totals['organizer_net'], 2) }}</strong></p>
    <table cellspacing="0" cellpadding="8" border="1" style="border-collapse:collapse;width:100%;border-color:#e5e7eb;">
        <thead><tr><th align="left">Event</th><th>Orders</th><th>Tickets</th><th>Free</th><th>Gross (TZS)</th><th>Organizer Net (TZS)</th></tr></thead>
        <tbody>
        @forelse ($groups as $group)
            <tr>
                <td>{{ $group['event'] }}</td>
                <td align="right">{{ $group['orders'] }}</td>
                <td align="right">{{ $group['tickets'] }}</td>
                <td align="right">{{ $group['free_tickets'] }}</td>
                <td align="right">{{ number_format($group['gross'], 2) }}</td>
                <td align="right">{{ number_format($group['organizer_net'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6">No confirmed paid orders for this day.</td></tr>
        @endforelse
        </tbody>
    </table>
    <p style="font-size:12px;color:#64748b;">This report counts orders currently marked paid, grouped by payment confirmation date. It excludes pending, refunded and partially refunded orders. It is not bank settlement confirmation or available payout balance. Figures may change if orders are later refunded.</p>
</body>
</html>
