@php
    use Illuminate\Support\Facades\Storage;

    $eventName =
        $event?->name
        ?? 'eLive Event';

    $organization =
        $event?->organization;

    $communicationTitle =
        $communication?->title
        ?? $subject
        ?? 'Event Communication';

    $communicationSummary =
        $communication?->summary
        ?? null;

    $publishedAt =
        $communication?->published_at
        ?? now();

    $heroTitle =
        $communication?->hero_title
        ?: $communicationTitle;

    $heroSubtitle =
        $communication?->hero_subtitle
        ?: optional($publishedAt)->format('d F Y');

    $heroImageUrl = null;

    if (
        $communication
        && filled($communication->hero_image_path)
    ) {
        $heroPath =
            ltrim(
                (string) $communication->hero_image_path,
                '/'
            );

        if (
            str_starts_with(
                $heroPath,
                'storage/'
            )
        ) {
            $heroPath =
                substr(
                    $heroPath,
                    strlen('storage/')
                );
        }

        if (
            Storage::disk('public')->exists(
                $heroPath
            )
        ) {
            $heroImageUrl =
                Storage::disk('public')->url(
                    $heroPath
                );
        }
    }

    if (
        blank($heroImageUrl)
        && $event
        && filled($event->banner_image)
    ) {
        $eventHeroPath =
            ltrim(
                (string) $event->banner_image,
                '/'
            );

        if (
            str_starts_with(
                $eventHeroPath,
                'storage/'
            )
        ) {
            $eventHeroPath =
                substr(
                    $eventHeroPath,
                    strlen('storage/')
                );
        }

        if (
            Storage::disk('public')->exists(
                $eventHeroPath
            )
        ) {
            $heroImageUrl =
                Storage::disk('public')->url(
                    $eventHeroPath
                );
        }
    }

    $logoUrl =
        url('/eLive-Logo.png');

    $sections =
        $communication?->sections
        ?? collect();

    $links =
        $communication?->links
        ?? collect();

    $attachments =
        $communication?->attachments
        ?? collect();

    $organizationName =
        $organization?->name;

    $organizationEmail =
        $organization?->email;

    $organizationPhone =
        $organization?->phone;

    $organizationWebsite =
        $organization?->website;
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>{{ $communicationTitle }}</title>
</head>

<body
    style="
        margin: 0;
        padding: 0;
        background: #f5f7fb;
        font-family: Arial, Helvetica, sans-serif;
        color: #1f2937;
    "
>
<table
    role="presentation"
    width="100%"
    cellpadding="0"
    cellspacing="0"
    border="0"
    style="
        width: 100%;
        background: #f5f7fb;
        margin: 0;
        padding: 24px 0;
    "
>
    <tr>
        <td align="center">
            <table
                role="presentation"
                width="640"
                cellpadding="0"
                cellspacing="0"
                border="0"
                style="
                    width: 100%;
                    max-width: 640px;
                    background: #ffffff;
                    border-radius: 14px;
                    overflow: hidden;
                    border: 1px solid #e6ebf2;
                "
            >
                {{-- Header --}}
                <tr>
                    <td
                        style="
                            padding: 18px 22px;
                            background: #ffffff;
                        "
                    >
                        <table
                            role="presentation"
                            width="100%"
                            cellpadding="0"
                            cellspacing="0"
                            border="0"
                        >
                            <tr>
                                <td
                                    valign="middle"
                                    style="width: 50%;"
                                >
                                    <img
                                        src="{{ $logoUrl }}"
                                        alt="eLive Events"
                                        width="105"
                                        style="
                                            display: block;
                                            width: 105px;
                                            max-width: 105px;
                                            height: auto;
                                            border: 0;
                                        "
                                    >
                                </td>

                                <td
                                    align="right"
                                    valign="middle"
                                    style="
                                        width: 50%;
                                        font-size: 11px;
                                        line-height: 1.4;
                                        color: #64748b;
                                    "
                                >
                                    <strong
                                        style="
                                            display: block;
                                            color: #161943;
                                            font-size: 12px;
                                        "
                                    >
                                        {{ $eventName }}
                                    </strong>

                                    @if(filled($event?->venue))
                                        <span>
                                            {{ $event->venue }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Hero --}}
                @if(filled($heroImageUrl))
                    <tr>
                        <td
                            background="{{ $heroImageUrl }}"
                            style="
                                background-image:
                                    linear-gradient(
                                        rgba(12, 18, 48, 0.55),
                                        rgba(12, 18, 48, 0.55)
                                    ),
                                    url('{{ $heroImageUrl }}');
                                background-size: cover;
                                background-position: center;
                                padding: 54px 30px 46px 30px;
                            "
                        >
                            <table
                                role="presentation"
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                            >
                                <tr>
                                    <td>
                                        <div
                                            style="
                                                font-size: 36px;
                                                line-height: 1.08;
                                                font-weight: 800;
                                                color: #ffffff;
                                                margin: 0 0 14px 0;
                                            "
                                        >
                                            {{ $heroTitle }}
                                        </div>

                                        @if(filled($heroSubtitle))
                                            <span
                                                style="
                                                    display: inline-block;
                                                    background: #ff9800;
                                                    color: #161943;
                                                    border-radius: 999px;
                                                    padding: 7px 12px;
                                                    font-size: 10px;
                                                    line-height: 1;
                                                    font-weight: 700;
                                                "
                                            >
                                                {{ $heroSubtitle }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif

                {{-- Main Content --}}
                <tr>
                    <td
                        style="
                            padding: 26px 28px 10px 28px;
                            background: #ffffff;
                        "
                    >
                        @if(filled($communicationSummary))
                            <div
                                style="
                                    color: #007ab2;
                                    font-size: 10px;
                                    line-height: 1;
                                    font-weight: 800;
                                    letter-spacing: 1.2px;
                                    text-transform: uppercase;
                                    margin-bottom: 12px;
                                "
                            >
                                Highlight
                            </div>

                            <div
                                style="
                                    font-size: 14px;
                                    line-height: 1.7;
                                    color: #475569;
                                    margin-bottom: 26px;
                                "
                            >
                                {{ $communicationSummary }}
                            </div>
                        @endif

                        @if(filled($communication?->body))
                            <div
                                style="
                                    font-size: 14px;
                                    line-height: 1.7;
                                    color: #475569;
                                    margin-bottom: 26px;
                                "
                            >
                                {!! $communication->body !!}
                            </div>
                        @endif

                        @if($sections->isNotEmpty())
                            <div
                                style="
                                    color: #007ab2;
                                    font-size: 10px;
                                    line-height: 1;
                                    font-weight: 800;
                                    letter-spacing: 1.2px;
                                    text-transform: uppercase;
                                    margin-bottom: 13px;
                                "
                            >
                                Today&apos;s Highlights
                            </div>

                            <table
                                role="presentation"
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                                style="margin-bottom: 24px;"
                            >
                                @foreach($sections->chunk(2) as $sectionRow)
                                    <tr>
                                        @foreach($sectionRow as $section)
                                            <td
                                                valign="top"
                                                width="50%"
                                                style="
                                                    width: 50%;
                                                    padding: 0 6px 12px 0;
                                                "
                                            >
                                                <table
                                                    role="presentation"
                                                    width="100%"
                                                    cellpadding="0"
                                                    cellspacing="0"
                                                    border="0"
                                                    style="
                                                        border: 1px solid #dfe6ef;
                                                        border-radius: 10px;
                                                        background: #ffffff;
                                                    "
                                                >
                                                    <tr>
                                                        <td
                                                            style="
                                                                padding: 14px 14px 15px 14px;
                                                            "
                                                        >
                                                            <div
                                                                style="
                                                                    width: 22px;
                                                                    height: 22px;
                                                                    line-height: 22px;
                                                                    text-align: center;
                                                                    border-radius: 50%;
                                                                    background: #eaf4ff;
                                                                    color: #007ab2;
                                                                    font-size: 11px;
                                                                    font-weight: 800;
                                                                    margin-bottom: 8px;
                                                                "
                                                            >
                                                                {{ $loop->parent->index * 2 + $loop->iteration }}
                                                            </div>

                                                            <div
                                                                style="
                                                                    font-size: 12px;
                                                                    line-height: 1.4;
                                                                    color: #161943;
                                                                    font-weight: 800;
                                                                    margin-bottom: 7px;
                                                                "
                                                            >
                                                                {{ $section->title }}
                                                            </div>

                                                            @if(filled($section->content))
                                                                <div
                                                                    style="
                                                                        font-size: 11px;
                                                                        line-height: 1.55;
                                                                        color: #64748b;
                                                                    "
                                                                >
                                                                    {!! $section->content !!}
                                                                </div>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        @endforeach

                                        @if($sectionRow->count() === 1)
                                            <td width="50%"></td>
                                        @endif
                                    </tr>
                                @endforeach
                            </table>
                        @endif

                        @if($links->isNotEmpty())
                            <div
                                style="
                                    color: #007ab2;
                                    font-size: 10px;
                                    line-height: 1;
                                    font-weight: 800;
                                    letter-spacing: 1.2px;
                                    text-transform: uppercase;
                                    margin-bottom: 12px;
                                "
                            >
                                Quick Links
                            </div>

                            <div
                                style="
                                    margin-bottom: 24px;
                                "
                            >
                                @foreach($links as $link)
                                    <a
                                        href="{{ $link->url }}"
                                        target="{{ $link->open_in_new_tab ? '_blank' : '_self' }}"
                                        style="
                                            display: inline-block;
                                            background: #233f7e;
                                            color: #ffffff;
                                            text-decoration: none;
                                            border-radius: 7px;
                                            padding: 10px 14px;
                                            margin: 0 6px 7px 0;
                                            font-size: 11px;
                                            line-height: 1;
                                            font-weight: 700;
                                        "
                                    >
                                        {{ $link->label }}
                                        ↗
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @if($attachments->isNotEmpty())
                            <div
                                style="
                                    color: #007ab2;
                                    font-size: 10px;
                                    line-height: 1;
                                    font-weight: 800;
                                    letter-spacing: 1.2px;
                                    text-transform: uppercase;
                                    margin-bottom: 12px;
                                "
                            >
                                Handouts / Attachments
                            </div>

                            <table
                                role="presentation"
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                                style="
                                    width: 100%;
                                    margin-bottom: 24px;
                                "
                            >
                                @foreach($attachments as $attachment)
                                    @php
                                        $attachmentUrl =
                                            Storage::disk('public')->url(
                                                ltrim(
                                                    str_replace(
                                                        'storage/',
                                                        '',
                                                        (string) $attachment->file_path
                                                    ),
                                                    '/'
                                                )
                                            );
                                    @endphp

                                    <tr>
                                        <td
                                            style="
                                                border: 1px solid #ffb74d;
                                                border-radius: 8px;
                                                padding: 11px 13px;
                                                background: #fffaf3;
                                            "
                                        >
                                            <table
                                                role="presentation"
                                                width="100%"
                                                cellpadding="0"
                                                cellspacing="0"
                                                border="0"
                                            >
                                                <tr>
                                                    <td
                                                        style="
                                                            font-size: 11px;
                                                            line-height: 1.35;
                                                            color: #a04e00;
                                                            font-weight: 700;
                                                        "
                                                    >
                                                        {{ $attachment->title }}
                                                    </td>

                                                    <td
                                                        align="right"
                                                        style="
                                                            font-size: 10px;
                                                            line-height: 1.35;
                                                        "
                                                    >
                                                        <a
                                                            href="{{ $attachmentUrl }}"
                                                            target="_blank"
                                                            style="
                                                                color: #a04e00;
                                                                text-decoration: none;
                                                                font-weight: 700;
                                                            "
                                                        >
                                                            Open
                                                        </a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="height: 8px;"></td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif

                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td
                        align="center"
                        style="
                            background: #233f7e;
                            color: #ffffff;
                            padding: 24px 20px 22px 20px;
                        "
                    >
                        @if(filled($organizationName))
                            <div
                                style="
                                    font-size: 11px;
                                    font-weight: 800;
                                    line-height: 1.4;
                                    margin-bottom: 4px;
                                "
                            >
                                {{ $organizationName }}
                            </div>
                        @endif

                        <div
                            style="
                                font-size: 11px;
                                line-height: 1.4;
                                font-weight: 700;
                            "
                        >
                            {{ $eventName }}
                        </div>

                        @if(filled($event?->venue))
                            <div
                                style="
                                    font-size: 10px;
                                    line-height: 1.5;
                                    color: #dbeafe;
                                    margin-top: 3px;
                                "
                            >
                                {{ $event->venue }}
                            </div>
                        @endif

                        @if(
                            filled($organizationEmail)
                            || filled($organizationPhone)
                            || filled($organizationWebsite)
                        )
                            <div
                                style="
                                    font-size: 9px;
                                    line-height: 1.6;
                                    color: #dbeafe;
                                    margin-top: 10px;
                                "
                            >
                                @if(filled($organizationEmail))
                                    {{ $organizationEmail }}
                                @endif

                                @if(
                                    filled($organizationEmail)
                                    && filled($organizationPhone)
                                )
                                    ·
                                @endif

                                @if(filled($organizationPhone))
                                    {{ $organizationPhone }}
                                @endif

                                @if(
                                    (
                                        filled($organizationEmail)
                                        || filled($organizationPhone)
                                    )
                                    && filled($organizationWebsite)
                                )
                                    ·
                                @endif

                                @if(filled($organizationWebsite))
                                    {{ $organizationWebsite }}
                                @endif
                            </div>
                        @endif

                        <div
                            style="
                                margin-top: 13px;
                                padding-top: 11px;
                                border-top: 1px solid rgba(255, 255, 255, 0.16);
                                font-size: 8px;
                                line-height: 1.4;
                                color: #bfdbfe;
                            "
                        >
                            Powered by eLive Events
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
