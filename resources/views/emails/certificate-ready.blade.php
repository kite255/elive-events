<!doctype html>
<html lang="en">
<body style="margin:0;background:#f7f8fc;font-family:Arial,sans-serif;color:#161943">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="100%" style="max-width:560px;background:#fff;border:1px solid #e6e8ef;border-radius:12px" cellpadding="0" cellspacing="0">
<tr><td style="padding:24px;background:#161943;color:white;font-size:20px;font-weight:bold">eLive Events</td></tr>
<tr><td style="padding:24px;font-size:15px;line-height:1.6">
<h1 style="font-size:23px;margin:0 0 16px">Your certificate is ready</h1>
<p>Dear {{ $attendeeName }},</p>
<p>Thank you for participating in <strong>{{ $eventName }}</strong>.</p>
<p>Your certificate is now available. Download and save your PDF using the link below.</p>
<p style="margin:24px 0"><a href="{{ $downloadUrl }}" style="display:inline-block;background:#007ab2;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none;font-weight:bold">Download certificate</a></p>
<p>Your certificate includes a QR code for authenticity verification. The download link is valid for 30 days.</p>
<p>Kind regards,<br>{{ $organizationName }}</p>
</td></tr>
<tr><td style="padding:16px 24px;border-top:1px solid #e6e8ef;color:#64748b;font-size:12px">Powered by eLive Events</td></tr>
</table></td></tr></table>
</body></html>
