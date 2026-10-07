<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $campaign->title }} | eLive Events</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/creato-font.css') }}">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body>
    <main style="max-width: 900px; margin: 0 auto; padding: 48px 20px;">
        <a href="{{ route('public.donations.index') }}">← All campaigns</a>

        <header style="margin:24px 0;">
            <h1>{{ $campaign->title }}</h1>
            <p>{{ $campaign->organization?->name }}</p>
            @if ($campaign->event)
                <p>{{ $campaign->event->name }}</p>
            @endif
        </header>

        @if ($campaign->banner_image_path)
            <img
                src="{{ IlluminateSupportStr::startsWith($campaign->banner_image_path, ['http://','https://'])
                    ? $campaign->banner_image_path
                    : asset('storage/' . ltrim($campaign->banner_image_path, '/')) }}"
                alt="{{ $campaign->title }}"
                style="max-width:100%;height:auto;border-radius:16px;margin-bottom:24px;"
            >
        @endif

        @if ($campaign->description)
            <section style="margin-bottom: 28px;">
                {!! nl2br(e($campaign->description)) !!}
            </section>
        @endif

        @if (
            $campaign->payment_mode === AppModelsDonationCampaign::PAYMENT_MODE_CLIENT_DIRECT
            && $campaign->direct_payment_behavior === AppModelsDonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY
        )
            <section>
                <h2>How to contribute</h2>
                <p>Payment is made directly to the campaign organizer using the details below.</p>

                @forelse ($campaign->paymentMethods as $method)
                    <article style="border:1px solid #e5e7eb;border-radius:12px;padding:18px;margin:14px 0;">
                        <h3>{{ $method->provider_name }}</h3>
                        @if ($method->account_name)
                            <p><strong>Account Name:</strong> {{ $method->account_name }}</p>
                        @endif
                        @if ($method->account_number_or_phone)
                            <p><strong>Account / Phone:</strong> {{ $method->account_number_or_phone }}</p>
                        @endif
                        @if ($method->instructions)
                            <p>{{ $method->instructions }}</p>
                        @endif
                    </article>
                @empty
                    <p>Payment instructions have not been published yet.</p>
                @endforelse
            </section>
        @else
            <section>
                <h2>Donation options</h2>
                <p>This campaign supports tracked donations. Online and tracked contribution forms will be available here as the donations workflow is completed.</p>
            </section>
        @endif
    </main>
</body>
</html>
