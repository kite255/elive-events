@php
    use Illuminate\Support\Facades\Storage;

    $primaryColor = $event->registration_primary_color ?: '#161943';

    $heroHeights = [
        'small' => '280px',
        'medium' => '420px',
        'large' => '560px',
    ];

    $heroHeight = $heroHeights[$communication->hero_height] ?? '420px';

    $heroImage = filled($communication->hero_image_path)
        ? Storage::disk('public')->url($communication->hero_image_path)
        : (
            filled($event->registration_banner_image_path)
                ? Storage::disk('public')->url($event->registration_banner_image_path)
                : null
        );

    $heroTitle = $communication->hero_title ?: $communication->title;

    $heroSubtitle = $communication->hero_subtitle
        ?: optional($communication->published_at)->format('d F Y');

    $alignment = $communication->hero_text_alignment === 'center'
        ? 'center'
        : 'left';
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $communication->title }} – {{ $event->name }}</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f6f8fc;
            color: #172033;
            font-family: Arial, Helvetica, sans-serif;
        }

        .page {
            width: min(1080px, calc(100% - 32px));
            margin: 32px auto 64px;
        }

        .event-header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: center;
            padding: 24px 28px;
            background: #fff;
            border: 1px solid #e6ebf2;
            border-radius: 18px 18px 0 0;
        }

        .event-header img {
            max-height: 62px;
            max-width: 220px;
            object-fit: contain;
        }

        .event-name {
            text-align: right;
        }

        .event-name strong {
            display: block;
            font-size: 18px;
            color: {{ $primaryColor }};
        }

        .hero {
            position: relative;
            min-height: {{ $heroHeight }};
            display: flex;
            align-items: flex-end;
            overflow: hidden;
            background:
                linear-gradient(135deg, {{ $primaryColor }}, #007AB2);
            background-position: center;
            background-size: cover;
        }

        .hero.has-image {
            background-image:
                @if($communication->hero_overlay_enabled)
                    linear-gradient(
                        90deg,
                        rgba(7, 14, 34, .86) 0%,
                        rgba(7, 14, 34, .45) 55%,
                        rgba(7, 14, 34, .12) 100%
                    ),
                @endif
                url('{{ $heroImage }}');
        }

        .hero-content {
            width: 100%;
            padding: 52px;
            color: #fff;
            text-align: {{ $alignment }};
        }

        .hero-title {
            margin: 0;
            max-width: 760px;
            font-size: clamp(42px, 7vw, 76px);
            line-height: .96;
            letter-spacing: -2px;
        }

        .hero-subtitle {
            display: inline-block;
            margin-top: 18px;
            padding: 9px 16px;
            border-radius: 999px;
            background: #FF9800;
            color: #111827;
            font-weight: 700;
        }

        .content-card {
            background: #fff;
            padding: 40px 48px;
            border: 1px solid #e6ebf2;
            border-top: 0;
        }

        .communication-type {
            display: inline-block;
            margin-bottom: 18px;
            color: #007AB2;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: 12px;
        }

        .summary {
            margin: 0 0 28px;
            color: #475569;
            font-size: 18px;
            line-height: 1.7;
        }

        .body {
            line-height: 1.75;
            font-size: 16px;
        }

        .section-heading {
            margin: 34px 0 16px;
            color: #007AB2;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .highlight-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .highlight-card {
            padding: 20px;
            border: 1px solid #e6ebf2;
            border-radius: 14px;
            background: #fff;
        }

        .highlight-number {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-weight: 800;
        }

        .highlight-card h3 {
            margin: 0 0 8px;
            color: {{ $primaryColor }};
            font-size: 17px;
        }

        .highlight-card .section-body {
            color: #64748b;
            line-height: 1.65;
        }

        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .quick-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 12px;
            background: {{ $primaryColor }};
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            transition: transform .15s ease, opacity .15s ease;
        }

        .quick-link:hover {
            transform: translateY(-1px);
            opacity: .92;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .gallery-item {
            overflow: hidden;
            border-radius: 14px;
            border: 1px solid #e6ebf2;
            background: #fff;
        }

        .gallery-item img {
            width: 100%;
            aspect-ratio: 4 / 3;
            display: block;
            object-fit: cover;
        }

        .gallery-caption {
            padding: 10px 12px;
            color: #64748b;
            font-size: 13px;
        }

        .attachments {
            display: grid;
            gap: 10px;
        }

        .attachment {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 16px;
            border: 1px solid #fed7aa;
            border-radius: 12px;
            background: #fff7ed;
            color: #9a3412;
            text-decoration: none;
            font-weight: 700;
        }

        .attachment small {
            color: #7c2d12;
            font-weight: 600;
        }

        .footer {
            padding: 30px 48px 26px;
            background: {{ $primaryColor }};
            color: #fff;
            text-align: center;
            border-radius: 0 0 18px 18px;
        }

        .footer-organization {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            line-height: 1.45;
        }

        .footer-event {
            margin-top: 4px;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.45;
            opacity: .94;
        }

        .footer-contact {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 7px 14px;
            margin-top: 12px;
            font-size: 12px;
        }

        .footer-contact a {
            color: #fff;
            text-decoration: none;
            opacity: .84;
        }

        .footer-contact a:hover {
            opacity: 1;
            text-decoration: underline;
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px 18px;
            margin-top: 14px;
        }

        .footer-links a {
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            opacity: .90;
        }

        .footer-links a:hover {
            opacity: 1;
            text-decoration: underline;
        }

        .footer-divider {
            width: 54px;
            height: 1px;
            margin: 18px auto 14px;
            background: rgba(255,255,255,.28);
        }

        .footer-powered {
            font-size: 11px;
            opacity: .72;
        }

        .footer-powered strong {
            color: #fff;
            font-weight: 800;
        }

        @media (max-width: 720px) {
            .page {
                width: 100%;
                margin: 0;
            }

            .event-header {
                border-radius: 0;
                padding: 18px;
            }

            .event-header img {
                max-width: 150px;
                max-height: 48px;
            }

            .hero-content {
                padding: 32px 22px;
            }

            .content-card,
            .footer {
                padding: 28px 22px;
            }

            .highlight-grid,
            .gallery-grid {
                grid-template-columns: 1fr;
            }

            .quick-link {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>

<body>
<div class="page">

    <header class="event-header">
        <div>
            @if(filled($event->registration_logo_path))
                <img
                    src="{{ Storage::disk('public')->url($event->registration_logo_path) }}"
                    alt="{{ $event->name }}"
                >
            @else
                <img
                    src="{{ url('/eLive-Logo.png') }}"
                    alt="eLive Events"
                >
            @endif
        </div>

        <div class="event-name">
            <strong>{{ $event->name }}</strong>

            @if(filled($event->venue))
                <span>{{ $event->venue }}</span>
            @endif
        </div>
    </header>

    @if($communication->hero_enabled)
        <section
            @class([
                'hero',
                'has-image' => filled($heroImage),
            ])
        >
            <div class="hero-content">
                <h1 class="hero-title">
                    {{ $heroTitle }}
                </h1>

                @if(filled($heroSubtitle))
                    <div class="hero-subtitle">
                        {{ $heroSubtitle }}
                    </div>
                @endif
            </div>
        </section>
    @endif

    <main class="content-card">

        <div class="communication-type">
            {{ str($communication->type)->replace('_', ' ')->title() }}
        </div>

        @if(! $communication->hero_enabled)
            <h1>{{ $communication->title }}</h1>
        @endif

        @if(filled($communication->summary))
            <p class="summary">
                {{ $communication->summary }}
            </p>
        @endif

        @if(filled($communication->body))
            <div class="body">
                {!! $communication->body !!}
            </div>
        @endif

        @if($communication->sections->isNotEmpty())
            <div class="section-heading">
                {{
                    $communication->type === 'highlight'
                        ? "Today's Highlights"
                        : 'Key Information'
                }}
            </div>

            <div class="highlight-grid">
                @foreach(
                    $communication->sections
                    as $index => $section
                )
                    <article class="highlight-card">
                        <div class="highlight-number">
                            {{ $index + 1 }}
                        </div>

                        <h3>{{ $section->title }}</h3>

                        @if(filled($section->content))
                            <div class="section-body">
                                {!! $section->content !!}
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif

        @if($communication->links->isNotEmpty())
            <div class="section-heading">
                Quick Links
            </div>

            <div class="quick-links">
                @foreach($communication->links as $link)
                    <a
                        class="quick-link"
                        href="{{ $link->url }}"
                        @if($link->open_in_new_tab)
                            target="_blank"
                            rel="noopener noreferrer"
                        @endif
                    >
                        <span>{{ $link->label }}</span>

                        <x-heroicon-o-arrow-top-right-on-square
                            aria-hidden="true"
                            style="
                                width:16px;
                                height:16px;
                                flex:0 0 auto;
                            "
                        />
                    </a>
                @endforeach
            </div>
        @endif

        @if($communication->images->isNotEmpty())
            <div class="section-heading">
                Photo Gallery
            </div>

            <div class="gallery-grid">
                @foreach($communication->images as $image)
                    <figure class="gallery-item">
                        <img
                            src="{{
                                Storage::disk('public')
                                    ->url($image->image_path)
                            }}"
                            alt="{{
                                $image->caption
                                    ?: $communication->title
                            }}"
                        >

                        @if(filled($image->caption))
                            <figcaption class="gallery-caption">
                                {{ $image->caption }}
                            </figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
        @endif

        @if($communication->attachments->isNotEmpty())
            <div class="section-heading">
                Handouts / Attachments
            </div>

            <div class="attachments">
                @foreach(
                    $communication->attachments
                    as $attachment
                )
                    <a
                        class="attachment"
                        href="{{
                            Storage::disk('public')
                                ->url($attachment->file_path)
                        }}"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <span>
                            {{ $attachment->title }}
                        </span>

                        <small>
                            {{
                                $attachment->file_type
                                    ?: strtoupper(
                                        pathinfo(
                                            $attachment->file_path,
                                            PATHINFO_EXTENSION
                                        )
                                    )
                            }}
                            · Open
                        </small>
                    </a>
                @endforeach
            </div>
        @endif

    </main>

    @php
        $organization =
            $event->organization;

        $organizationName =
            $organization?->name;

        $organizationEmail =
            $organization?->email;

        $organizationPhone =
            $organization?->phone;

        $organizationWebsite =
            $organization?->website;

        $eventUrl =
            url(
                '/events/'
                . $event->slug
            );
    @endphp

    <footer class="footer">
        @if(filled($organizationName))
            <div class="footer-organization">
                {{ $organizationName }}
            </div>
        @endif

        <div class="footer-event">
            {{ $event->name }}
        </div>

        @if(
            filled($organizationEmail)
            || filled($organizationPhone)
            || filled($organizationWebsite)
        )
            <div class="footer-contact">
                @if(filled($organizationEmail))
                    <a href="mailto:{{ $organizationEmail }}">
                        {{ $organizationEmail }}
                    </a>
                @endif

                @if(filled($organizationPhone))
                    <a href="tel:{{ preg_replace('/\s+/', '', $organizationPhone) }}">
                        {{ $organizationPhone }}
                    </a>
                @endif

                @if(filled($organizationWebsite))
                    <a
                        href="{{
                            str_starts_with(
                                $organizationWebsite,
                                'http'
                            )
                                ? $organizationWebsite
                                : 'https://' . $organizationWebsite
                        }}"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Website
                    </a>
                @endif
            </div>
        @endif

        <nav
            class="footer-links"
            aria-label="Communication footer links"
        >
            <a href="{{ $eventUrl }}">
                Event Page
            </a>

            <a href="{{ url('/') }}">
                eLive Events
            </a>
        </nav>

        <div class="footer-divider"></div>

        <div class="footer-powered">
            Powered by
            <strong>eLive Events</strong>
        </div>
    </footer>

</div>
</body>
</html>
