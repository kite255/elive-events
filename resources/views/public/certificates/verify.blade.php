<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Certificate verification | eLive Events</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f7f8fc; color: #161943; font-family: Arial, sans-serif; }
        main { width: min(100% - 32px, 560px); margin: 48px auto; }
        .brand { font-size: 22px; font-weight: 700; margin-bottom: 24px; }
        .card { background: white; border: 1px solid #e6e8ef; border-radius: 16px; padding: 28px; }
        h1 { font-size: 25px; margin: 0 0 12px; }
        p { font-size: 14px; line-height: 1.5; color: #525b70; }
        .status { font-weight: 700; color: {{ $valid ? '#166534' : '#991b1b' }}; }
        dl { margin: 24px 0 0; }
        dt { font-size: 12px; color: #525b70; margin-top: 16px; }
        dd { margin: 5px 0 0; font-size: 15px; overflow-wrap: anywhere; }
        footer { margin-top: 24px; font-size: 12px; color: #525b70; text-align: center; }
    </style>
</head>
<body>
<main>
    <div class="brand">eLive Events</div>
    <div class="card">
        <h1>Certificate verification</h1>
        <p class="status">{{ $valid ? 'Valid certificate' : 'Revoked certificate' }}</p>
        <p>{{ $valid ? 'This certificate is recorded as issued by the event organizer.' : 'This certificate is no longer valid.' }}</p>
        <dl>
            <dt>Certificate holder</dt><dd>{{ $attendeeName }}</dd>
            <dt>Event</dt><dd>{{ $eventName }}</dd>
            <dt>Organizer</dt><dd>{{ $organizationName }}</dd>
            <dt>Certificate number</dt><dd>{{ $number }}</dd>
            <dt>Issued on</dt><dd>{{ $issuedDate }}</dd>
        </dl>
    </div>
    <footer>Powered by eLive Events</footer>
</main>
</body>
</html>
