<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation Status | eLive Events</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/creato-font.css') }}">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body>
    <main style="max-width:760px;margin:0 auto;padding:48px 20px;">
        <a href="{{ route('public.donations.show', ['campaign' => $donation->campaign->slug]) }}">
            ← Back to campaign
        </a>

        <section style="margin-top:24px;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;">
            <h1>Donation Status</h1>

            <p><strong>Reference:</strong> {{ $donation->reference }}</p>
            <p><strong>Campaign:</strong> {{ $donation->campaign->title }}</p>
            <p><strong>Amount:</strong> {{ $donation->currency }} {{ number_format((float) $donation->amount) }}</p>
            <p>
                <strong>Status:</strong>
                {{ match ($donation->status) {
                    'pending' => 'Pending',
                    'awaiting_payment' => 'Awaiting Payment',
                    'awaiting_verification' => 'Awaiting Verification',
                    'completed' => 'Completed',
                    'rejected' => 'Rejected',
                    'failed' => 'Failed',
                    'cancelled' => 'Cancelled',
                    default => ucfirst(str_replace('_', ' ', (string) $donation->status)),
                } }}
            </p>

            @if ($donation->completed_at)
                <p><strong>Completed:</strong> {{ $donation->completed_at->format('d M Y, H:i') }}</p>
            @endif
        </section>
    </main>
</body>
</html>
