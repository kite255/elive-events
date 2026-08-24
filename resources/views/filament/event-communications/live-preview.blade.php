@php
    use Illuminate\Support\Facades\Storage;
    use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

    $primary =
        $event->registration_primary_color
        ?: '#161943';

    $blue = '#007AB2';
    $orange = '#FF9800';

    $labels = [
        'announcement' =>
            'Announcement',

        'highlight' =>
            "Today's Highlights",

        'update' =>
            'Event Update',

        'schedule' =>
            'Schedule / Program',

        'reminder' =>
            'Reminder',

        'notice' =>
            'Important Notice',

        'emergency' =>
            'Emergency Alert',

        'summary' =>
            'Event Summary',

        'custom' =>
            'Custom Communication',
    ];

    $title =
        filled(
            $data['title']
            ?? null
        )
            ? $data['title']
            : 'Communication Title';

    $heroTitle =
        filled(
            $data['hero_title']
            ?? null
        )
            ? $data['hero_title']
            : $title;

    $heroSubtitle =
        $data['hero_subtitle']
        ?? null;

    $heroImageValue =
        $data['hero_image_path']
        ?? null;

    if (is_array($heroImageValue)) {
        $heroImageValue =
            collect(
                $heroImageValue
            )
                ->filter()
                ->first();
    }

    $heroImage = null;

    if (
        $heroImageValue
        instanceof TemporaryUploadedFile
    ) {
        try {
            $heroImage =
                $heroImageValue
                    ->temporaryUrl();
        } catch (\Throwable) {
            $heroImage = null;
        }
    } elseif (
        is_string(
            $heroImageValue
        )
        && filled(
            $heroImageValue
        )
    ) {
        $heroImage =
            Storage::disk('public')
                ->url(
                    $heroImageValue
                );
    }

    if (
        ! $heroImage
        && filled(
            $event
                ->registration_banner_image_path
        )
    ) {
        $heroImage =
            Storage::disk('public')
                ->url(
                    $event
                        ->registration_banner_image_path
                );
    }

    $sections =
        collect(
            $data['sections']
            ?? []
        )
            ->values()
            ->filter(
                fn ($section): bool =>
                    is_array(
                        $section
                    )
                    && filled(
                        $section['title']
                        ?? null
                    )
            );

    $links =
        collect(
            $data['links']
            ?? []
        )
            ->values()
            ->filter(
                fn ($link): bool =>
                    is_array(
                        $link
                    )
                    && filled(
                        $link['label']
                        ?? null
                    )
                    && filled(
                        $link['url']
                        ?? null
                    )
            );

    $images =
        collect(
            $data['images']
            ?? []
        )
            ->values()
            ->filter(
                fn ($image): bool =>
                    is_array(
                        $image
                    )
                    && filled(
                        $image['image_path']
                        ?? null
                    )
            );

    $attachments =
        collect(
            $data['attachments']
            ?? []
        )
            ->values()
            ->filter(
                fn ($attachment): bool =>
                    is_array(
                        $attachment
                    )
                    && filled(
                        $attachment['title']
                        ?? null
                    )
            );

    $heights = [
        'small' =>
            '190px',

        'medium' =>
            '260px',

        'large' =>
            '330px',
    ];

    $heroHeight =
        $heights[
            $data['hero_height']
            ?? 'medium'
        ]
        ?? '260px';

    $alignment =
        (
            $data[
                'hero_text_alignment'
            ]
            ?? 'left'
        ) === 'center'
            ? 'center'
            : 'left';

    /*
    |--------------------------------------------------------------------------
    | RichEditor live-state renderer
    |--------------------------------------------------------------------------
    |
    | Filament RichEditor may expose its live state as an array while the
    | user is typing. Render both HTML strings and structured array state
    | safely without triggering "Array to string conversion".
    |
    */

    $extractRichText = function (
        mixed $value
    ) use (&$extractRichText): string {
        if (! is_array($value)) {
            return '';
        }

        $parts = [];

        if (
            array_key_exists(
                'text',
                $value
            )
            && is_string(
                $value['text']
            )
        ) {
            $text = trim(
                $value['text']
            );

            if ($text !== '') {
                $parts[] = $text;
            }
        }

        foreach (
            $value
            as $key => $child
        ) {
            if ($key === 'text') {
                continue;
            }

            if (! is_array($child)) {
                continue;
            }

            $childText =
                trim(
                    $extractRichText(
                        $child
                    )
                );

            if ($childText !== '') {
                $parts[] =
                    $childText;
            }
        }

        return implode(
            "\n",
            $parts
        );
    };

    $renderRichContent = function (
        mixed $value
    ) use ($extractRichText): string {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            /*
             * Saved RichEditor values are already HTML.
             */
            return $value;
        }

        if (! is_array($value)) {
            return e(
                (string) $value
            );
        }

        $text =
            trim(
                $extractRichText(
                    $value
                )
            );

        if ($text === '') {
            return '';
        }

        return nl2br(
            e($text)
        );
    };
@endphp

<div
    style="
        overflow:hidden;
        border:1px solid #e6ebf2;
        border-radius:16px;
        background:#ffffff;
        box-shadow:
            0 8px 24px
            rgba(22,25,67,.06);
    "
>
    <div
        style="
            display:flex;
            justify-content:
                space-between;
            align-items:center;
            gap:14px;
            padding:16px 18px;
            border-bottom:
                1px solid #e6ebf2;
        "
    >
        <div>
            <strong
                style="
                    color:{{ $primary }};
                "
            >
                {{ $event->name }}
            </strong>

            @if(filled($event->venue))
                <div
                    style="
                        margin-top:3px;
                        color:#64748b;
                        font-size:11px;
                    "
                >
                    {{ $event->venue }}
                </div>
            @endif
        </div>

        <span
            style="
                color:{{ $blue }};
                font-size:10px;
                font-weight:800;
                text-transform:uppercase;
                letter-spacing:.06em;
            "
        >
            {{
                $labels[
                    $data['type']
                    ?? 'announcement'
                ]
                ?? 'Communication'
            }}
        </span>
    </div>

    @if($data['hero_enabled'] ?? true)
        <div
            style="
                min-height:
                    {{ $heroHeight }};
                display:flex;
                align-items:flex-end;
                background:
                    @if(filled($heroImage))
                        @if(
                            $data[
                                'hero_overlay_enabled'
                            ]
                            ?? true
                        )
                            linear-gradient(
                                90deg,
                                rgba(
                                    7,14,34,.88
                                ),
                                rgba(
                                    7,14,34,.30
                                )
                            ),
                        @endif
                        url('{{ $heroImage }}')
                    @else
                        linear-gradient(
                            135deg,
                            {{ $primary }},
                            {{ $blue }}
                        )
                    @endif;
                background-size:cover;
                background-position:center;
            "
        >
            <div
                style="
                    width:100%;
                    padding:26px;
                    color:#ffffff;
                    text-align:
                        {{ $alignment }};
                "
            >
                <h2
                    style="
                        margin:0;
                        font-size:34px;
                        line-height:1.05;
                        font-weight:800;
                    "
                >
                    {{ $heroTitle }}
                </h2>

                @if(filled($heroSubtitle))
                    <span
                        style="
                            display:inline-block;
                            margin-top:12px;
                            padding:6px 11px;
                            border-radius:999px;
                            background:
                                {{ $orange }};
                            color:#111827;
                            font-size:11px;
                            font-weight:800;
                        "
                    >
                        {{ $heroSubtitle }}
                    </span>
                @endif
            </div>
        </div>
    @endif

    <div style="padding:22px;">
        @if(! ($data['hero_enabled'] ?? true))
            <h2
                style="
                    margin:0 0 14px;
                    color:{{ $primary }};
                    font-size:26px;
                "
            >
                {{ $title }}
            </h2>
        @endif

        @if(filled($data['summary'] ?? null))
            <p
                style="
                    margin:0 0 18px;
                    color:#475569;
                    line-height:1.65;
                    font-size:13px;
                "
            >
                {{ $data['summary'] }}
            </p>
        @endif

        @if(filled($data['body'] ?? null))
            <div
                style="
                    margin-bottom:22px;
                    color:#334155;
                    line-height:1.65;
                    font-size:13px;
                "
            >
                {!! $renderRichContent($data['body'] ?? null) !!}
            </div>
        @endif

        @if($sections->isNotEmpty())
            <div
                style="
                    margin:22px 0 14px;
                    color:{{ $blue }};
                    font-weight:800;
                    text-align:center;
                    text-transform:uppercase;
                    letter-spacing:.05em;
                    font-size:12px;
                "
            >
                {{
                    ($data['type'] ?? null)
                    === 'highlight'
                        ? "Today's Highlights"
                        : 'Key Information'
                }}
            </div>

            <div
                style="
                    display:grid;
                    grid-template-columns:
                        repeat(
                            2,
                            minmax(0,1fr)
                        );
                    gap:10px;
                "
            >
                @foreach(
                    $sections
                    as $index => $section
                )
                    <div
                        style="
                            padding:14px;
                            border:
                                1px solid
                                #e6ebf2;
                            border-radius:12px;
                        "
                    >
                        <div
                            style="
                                width:26px;
                                height:26px;
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                margin-bottom:9px;
                                border-radius:999px;
                                background:#dbeafe;
                                color:#1d4ed8;
                                font-size:11px;
                                font-weight:800;
                            "
                        >
                            {{ $index + 1 }}
                        </div>

                        <strong
                            style="
                                color:
                                    {{ $primary }};
                                font-size:13px;
                            "
                        >
                            {{ $section['title'] }}
                        </strong>

                        @if(
                            filled(
                                $section[
                                    'content'
                                ]
                                ?? null
                            )
                        )
                            <div
                                style="
                                    margin-top:7px;
                                    color:#64748b;
                                    line-height:1.55;
                                    font-size:11px;
                                "
                            >
                                {!! $renderRichContent($section['content'] ?? null) !!}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if($links->isNotEmpty())
            <div
                style="
                    margin-top:20px;
                "
            >
                <div
                    style="
                        margin-bottom:10px;
                        color:{{ $blue }};
                        font-size:11px;
                        font-weight:800;
                        text-transform:uppercase;
                        letter-spacing:.05em;
                    "
                >
                    Quick Links
                </div>

                <div
                    style="
                        display:flex;
                        flex-wrap:wrap;
                        gap:8px;
                    "
                >
                    @foreach($links as $link)
                        <span
                            style="
                                display:inline-flex;
                                align-items:center;
                                gap:6px;
                                padding:9px 12px;
                                border-radius:10px;
                                background:{{ $primary }};
                                color:#ffffff;
                                font-size:10px;
                                font-weight:700;
                            "
                        >
                            {{ $link['label'] }}
                            <span aria-hidden="true">↗</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        @if($images->isNotEmpty())
            <div
                style="
                    margin-top:20px;
                    padding:14px;
                    border:
                        1px solid #bbf7d0;
                    border-radius:12px;
                    background:#ecfdf5;
                "
            >
                <strong
                    style="
                        display:block;
                        color:#166534;
                        font-size:12px;
                    "
                >
                    View Event Photos
                </strong>

                <span
                    style="
                        display:block;
                        margin-top:4px;
                        color:#64748b;
                        font-size:10px;
                        line-height:1.5;
                    "
                >
                    {{
                        $images->count()
                    }}
                    {{
                        $images->count() === 1
                            ? 'photo'
                            : 'photos'
                    }}
                    available in this communication.
                </span>
            </div>
        @endif

        @if($attachments->isNotEmpty())
            <div
                style="
                    margin-top:10px;
                    padding:14px;
                    border:
                        1px solid #fed7aa;
                    border-radius:12px;
                    background:#fff7ed;
                "
            >
                <strong
                    style="
                        color:#9a3412;
                        font-size:12px;
                    "
                >
                    Handouts / Documents
                </strong>

                @foreach(
                    $attachments
                    as $attachment
                )
                    <div
                        style="
                            margin-top:8px;
                            padding-top:8px;
                            border-top:
                                1px solid #fed7aa;
                            color:#64748b;
                            font-size:10px;
                        "
                    >
                        {{ $attachment['title'] }}

                        @if(
                            filled(
                                $attachment[
                                    'file_type'
                                ]
                                ?? null
                            )
                        )
                            ·
                            {{
                                strtoupper(
                                    $attachment[
                                        'file_type'
                                    ]
                                )
                            }}
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @php
        $organizationName =
            $event->organization?->name;

        $organizationEmail =
            $event->organization?->email;

        $organizationPhone =
            $event->organization?->phone;
    @endphp

    <div
        style="
            padding:18px 20px;
            background:{{ $primary }};
            color:#ffffff;
            text-align:center;
        "
    >
        @if(filled($organizationName))
            <div
                style="
                    font-size:11px;
                    font-weight:800;
                    line-height:1.4;
                "
            >
                {{ $organizationName }}
            </div>
        @endif

        <div
            style="
                margin-top:
                    {{ filled($organizationName) ? '3px' : '0' }};
                font-size:10px;
                font-weight:700;
                line-height:1.4;
                opacity:.96;
            "
        >
            {{ $event->name }}
        </div>

        @if(
            filled($organizationEmail)
            || filled($organizationPhone)
        )
            <div
                style="
                    margin-top:7px;
                    font-size:9px;
                    line-height:1.5;
                    opacity:.82;
                "
            >
                @if(filled($organizationEmail))
                    {{ $organizationEmail }}
                @endif

                @if(
                    filled($organizationEmail)
                    && filled($organizationPhone)
                )
                    <span> &nbsp;•&nbsp; </span>
                @endif

                @if(filled($organizationPhone))
                    {{ $organizationPhone }}
                @endif
            </div>
        @endif

        <div
            style="
                width:42px;
                height:1px;
                margin:11px auto 9px;
                background:rgba(255,255,255,.28);
            "
        ></div>

        <div
            style="
                font-size:8px;
                line-height:1.4;
                opacity:.78;
            "
        >
            Powered by
            <strong
                style="
                    font-weight:800;
                    color:#ffffff;
                "
            >
                eLive Events
            </strong>
        </div>
    </div>
</div>
