<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donations | eLive Events</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/creato-font.css') }}">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body>
    <main style="max-width: 1100px; margin: 0 auto; padding: 48px 20px;">
        <header style="margin-bottom: 32px;">
            <a href="{{ route('home') }}">eLive Events</a>
            <h1>Support a Campaign</h1>
            <p>Choose an active campaign to view its support options.</p>
        </header>

        @if ($campaigns->count())
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;">
                @foreach ($campaigns as $campaign)
                    <article style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:22px;">
                        <h2>{{ $campaign->title }}</h2>
                        <p>{{ $campaign->organization?->name }}</p>
                        @if ($campaign->event)
                            <p>{{ $campaign->event->name }}</p>
                        @endif
                        @if ($campaign->description)
                            <p>{{ IlluminateSupportStr::limit($campaign->description, 160) }}</p>
                        @endif
                        <a href="{{ route('public.donations.show', ['campaign' => $campaign->slug]) }}">
                            View campaign
                        </a>
                    </article>
                @endforeach
            </div>

            <div style="margin-top: 28px;">
                {{ $campaigns->links() }}
            </div>
        @else
            <p>No public donation campaigns are available right now.</p>
        @endif
    </main>
</body>
</html>
