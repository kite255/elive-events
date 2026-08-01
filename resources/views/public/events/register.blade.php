<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $event->registration_welcome_title ?: 'Register for ' . $event->name }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body style="
    margin: 0;
    background: {{ $branding['background_color'] }};
    color: #0f172a;
    font-family: Arial, sans-serif;
">
    <div style="min-height: 100vh; padding: 28px 16px;">
        <div style="max-width: 980px; margin: 0 auto;">
            <div style="
                background: #ffffff;
                border-radius: 24px;
                overflow: hidden;
                box-shadow: 0 16px 35px rgba(15, 23, 42, 0.12);
                border: 1px solid #e5e7eb;
            ">
                @if ($branding['banner'])
                    <div style="
                        height: 230px;
                        background-image: url('{{ asset('storage/' . $branding['banner']) }}');
                        background-size: cover;
                        background-position: center;
                    "></div>
                @else
                    <div style="height: 16px; background: {{ $branding['primary_color'] }};"></div>
                @endif

                <div style="padding: 28px;">
                    <div style="display: flex; gap: 18px; align-items: center; flex-wrap: wrap;">
                        @if ($branding['logo'])
                            <img
                                src="{{ asset('storage/' . $branding['logo']) }}"
                                alt="Logo"
                                style="
                                    width: 72px;
                                    height: 72px;
                                    object-fit: contain;
                                    border-radius: 16px;
                                    border: 1px solid #e5e7eb;
                                    padding: 8px;
                                    background: white;
                                "
                            >
                        @endif

                        <div style="flex: 1; min-width: 260px;">
                            <h1 style="
                                margin: 0;
                                color: {{ $branding['primary_color'] }};
                                font-size: 30px;
                                line-height: 1.2;
                                font-weight: 900;
                            ">
                                {{ $event->registration_welcome_title ?: 'Register for ' . $event->name }}
                            </h1>

                            <p style="margin: 8px 0 0 0; color: #64748b; font-size: 15px;">
                                {{ $event->registration_welcome_message ?: 'Complete the form below to register for this event.' }}
                            </p>
                        </div>
                    </div>

                    <div style="
                        margin-top: 24px;
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                        gap: 12px;
                    ">
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                            <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Event</div>
                            <div style="font-size:15px;font-weight:800;margin-top:5px;">{{ $event->name }}</div>
                        </div>

                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                            <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Venue</div>
                            <div style="font-size:15px;font-weight:800;margin-top:5px;">{{ $event->venue ?: 'To be announced' }}</div>
                        </div>

                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                            <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Date</div>
                            <div style="font-size:15px;font-weight:800;margin-top:5px;">
                                {{ $event->starts_at?->format('d M Y, H:i') ?? 'To be announced' }}
                            </div>
                        </div>

                    </div>

                    @if (session('error'))
                        <div style="
                            margin-top: 22px;
                            background: #fee2e2;
                            color: #991b1b;
                            border: 1px solid #fecaca;
                            border-radius: 14px;
                            padding: 14px;
                            font-weight: 700;
                        ">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div style="
                            margin-top: 22px;
                            background: #fee2e2;
                            color: #991b1b;
                            border: 1px solid #fecaca;
                            border-radius: 14px;
                            padding: 14px;
                            font-weight: 700;
                        ">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    @if (! $isOpen)
                        <div style="
                            margin-top: 24px;
                            background: #fff7ed;
                            color: #9a3412;
                            border: 1px solid #fed7aa;
                            border-radius: 18px;
                            padding: 20px;
                        ">
                            <h2 style="margin:0;font-size:20px;font-weight:900;">Registration Closed</h2>
                            <p style="margin:8px 0 0 0;">Public registration for this event is currently closed.</p>
                        </div>
                    @elseif ($isFull && ! $waitlistEnabled)
                        <div style="
                            margin-top: 24px;
                            background: #fee2e2;
                            color: #991b1b;
                            border: 1px solid #fecaca;
                            border-radius: 18px;
                            padding: 20px;
                        ">
                            <h2 style="margin:0;font-size:20px;font-weight:900;">Registration Full</h2>

                            <p style="margin:8px 0 0 0;">
                                {{ $event->registration_waitlist_message ?: 'This event has reached its registration capacity.' }}
                            </p>

                        </div>
                    @else
                        @if ($isFull && $waitlistEnabled)
                            <div style="
                                margin-top: 24px;
                                background: #fff7ed;
                                color: #9a3412;
                                border: 1px solid #fed7aa;
                                border-radius: 18px;
                                padding: 20px;
                            ">
                                <h2 style="margin:0;font-size:20px;font-weight:900;">Join Waitlist</h2>
                                <p style="margin:8px 0 0 0;">
                                    {{ $event->registration_waitlist_message ?: 'The event is full, but you can still join the waitlist.' }}
                                </p>

                                @if (! empty($registrationStats['waitlisted']))
                                    <p style="margin:8px 0 0 0;font-weight:800;">
                                        Waitlisted: {{ $registrationStats['waitlisted'] }}
                                    </p>
                                @endif
                            </div>
                        @endif

                        <form method="POST" action="{{ route('public.registration.store', $event) }}" style="margin-top: 28px;">
                            @csrf

                            @if ($isFull && $waitlistEnabled)
                                <input type="hidden" name="join_waitlist" value="1">
                            @endif

                            <div style="
                                background: #ffffff;
                                border: 1px solid #e2e8f0;
                                border-radius: 20px;
                                padding: 22px;
                                box-shadow: 0 8px 22px rgba(15,23,42,0.06);
                            ">
                                <h2 style="
                                    margin: 0 0 18px 0;
                                    color: {{ $branding['primary_color'] }};
                                    font-size: 22px;
                                    font-weight: 900;
                                ">
                                    Attendee Information
                                </h2>

                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
                                    <div>
                                        <label style="display:block;font-weight:800;margin-bottom:7px;">Full Name *</label>
                                        <input
                                            name="full_name"
                                            value="{{ old('full_name') }}"
                                            required
                                            style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                        >
                                    </div>

                                    <div>
                                        <label style="display:block;font-weight:800;margin-bottom:7px;">Phone Number</label>
                                        <input
                                            type="tel"
                                            name="phone"
                                            value="{{ old('phone') }}"
                                            placeholder="255712345678"
                                            style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                        >
                                    </div>

                                    <div>
                                        <label style="display:block;font-weight:800;margin-bottom:7px;">Email Address</label>
                                        <input
                                            type="email"
                                            name="email"
                                            value="{{ old('email') }}"
                                            style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                        >
                                    </div>

                                    <div>
                                        <label style="display:block;font-weight:800;margin-bottom:7px;">Organization / Company</label>
                                        <input
                                            name="organization_name"
                                            value="{{ old('organization_name') }}"
                                            style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                        >
                                    </div>

                                    <div>
                                        <label style="display:block;font-weight:800;margin-bottom:7px;">Position / Title</label>
                                        <input
                                            name="position"
                                            value="{{ old('position') }}"
                                            style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                        >
                                    </div>

                                    @if (($categories ?? collect())->count())
                                        <div>
                                            <label style="display:block;font-weight:800;margin-bottom:7px;">Category</label>
                                            <select
                                                name="category_id"
                                                style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;background:white;"
                                            >
                                                <option value="">Select category</option>
                                                @foreach ($categories as $category)
                                                    <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>
                                                        {{ $category->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif

                                    @if (($badgeTypes ?? collect())->count())
                                        <div>
                                            <label style="display:block;font-weight:800;margin-bottom:7px;">Badge Type</label>
                                            <select
                                                name="badge_type_id"
                                                style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;background:white;"
                                            >
                                                <option value="">Select badge type</option>
                                                @foreach ($badgeTypes as $badgeType)
                                                    <option value="{{ $badgeType->id }}" @selected(old('badge_type_id') == $badgeType->id)>
                                                        {{ $badgeType->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if (($eventDays ?? collect())->count())
                                @php
                                    $oldEventDays = collect(old('event_days', []))
                                        ->map(fn ($id) => (int) $id)
                                        ->all();
                                @endphp

                                <div style="
                                    margin-top:22px;
                                    background:#ffffff;
                                    border:1px solid #e2e8f0;
                                    border-radius:20px;
                                    padding:22px;
                                    box-shadow:0 8px 22px rgba(15,23,42,0.06);
                                ">
                                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                                        <div>
                                            <h2 style="
                                                margin:0;
                                                color:{{ $branding['primary_color'] }};
                                                font-size:22px;
                                                font-weight:900;
                                            ">
                                                Attendance Days
                                            </h2>

                                            <p style="margin:7px 0 0;color:#64748b;font-size:14px;line-height:1.5;">
                                                Select all days you expect to attend.
                                            </p>
                                        </div>

                                        <div style="
                                            background:#eef2ff;
                                            border:1px solid #c7d2fe;
                                            color:#3730a3;
                                            border-radius:12px;
                                            padding:10px 12px;
                                            font-size:12px;
                                            font-weight:800;
                                        ">
                                            Select at least one day
                                        </div>
                                    </div>

                                    <div style="
                                        display:grid;
                                        grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
                                        gap:12px;
                                        margin-top:18px;
                                    ">
                                        @foreach ($eventDays as $day)
                                            @php
                                                $daySelected = in_array(
                                                    (int) $day->id,
                                                    $oldEventDays,
                                                    true
                                                );
                                            @endphp

                                            <label style="
                                                display:flex;
                                                align-items:flex-start;
                                                gap:12px;
                                                padding:16px;
                                                border:1px solid #e2e8f0;
                                                border-radius:16px;
                                                background:#f8fafc;
                                                cursor:pointer;
                                            ">
                                                <input
                                                    type="checkbox"
                                                    name="event_days[]"
                                                    value="{{ $day->id }}"
                                                    @checked($daySelected)
                                                    style="
                                                        width:19px;
                                                        height:19px;
                                                        margin-top:2px;
                                                        flex:0 0 auto;
                                                    "
                                                >

                                                <span style="display:block;min-width:0;">
                                                    <strong style="
                                                        display:block;
                                                        color:#0f172a;
                                                        font-size:15px;
                                                        line-height:1.35;
                                                    ">
                                                        {{ $day->name }}
                                                    </strong>

                                                    <span style="
                                                        display:block;
                                                        margin-top:5px;
                                                        color:#64748b;
                                                        font-size:13px;
                                                        line-height:1.5;
                                                    ">
                                                        {{ $day->event_date?->format('d M Y') }}

                                                        @if ($day->starts_at)
                                                            — {{ $day->starts_at?->format('H:i') }}
                                                        @endif

                                                        @if ($day->ends_at)
                                                            to {{ $day->ends_at?->format('H:i') }}
                                                        @endif
                                                    </span>

                                                    @if (filled($day->venue_name))
                                                        <span style="
                                                            display:block;
                                                            margin-top:4px;
                                                            color:#475569;
                                                            font-size:12px;
                                                            font-weight:700;
                                                        ">
                                                            {{ $day->venue_name }}
                                                        </span>
                                                    @endif
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>

                                    @error('event_days')
                                        <div style="
                                            margin-top:12px;
                                            color:#dc2626;
                                            font-size:13px;
                                            font-weight:800;
                                        ">
                                            {{ $message }}
                                        </div>
                                    @enderror

                                    @error('event_days.*')
                                        <div style="
                                            margin-top:12px;
                                            color:#dc2626;
                                            font-size:13px;
                                            font-weight:800;
                                        ">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            @endif

                            @if (($fields ?? collect())->count())
                                <div style="
                                    margin-top: 22px;
                                    background: #ffffff;
                                    border: 1px solid #e2e8f0;
                                    border-radius: 20px;
                                    padding: 22px;
                                    box-shadow: 0 8px 22px rgba(15,23,42,0.06);
                                ">
                                    <h2 style="
                                        margin: 0 0 18px 0;
                                        color: {{ $branding['primary_color'] }};
                                        font-size: 22px;
                                        font-weight: 900;
                                    ">
                                        Additional Information
                                    </h2>

                                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
                                        @foreach ($fields as $field)
                                            @php
                                                $fieldName = 'answers[' . $field->id . ']';
                                                $oldValue = old('answers.' . $field->id);
                                                $label = $field->label ?? $field->name ?? 'Field';
                                                $type = $field->field_type ?? $field->type ?? 'text';
                                                $placeholder = $field->placeholder ?? '';
                                                $helpText = $field->help_text ?? null;
                                                $isRequired = (bool) ($field->is_required ?? false);

                                                $rawOptions = $field->options ?? [];

                                                if (is_string($rawOptions)) {
                                                    $decodedOptions = json_decode($rawOptions, true);
                                                    $rawOptions = is_array($decodedOptions) ? $decodedOptions : [];
                                                }

                                                $options = collect($rawOptions);
                                            @endphp

                                            <div style="{{ in_array($type, ['textarea', 'checkbox', 'radio'], true) ? 'grid-column:1/-1;' : '' }}">
                                                <label style="display:block;font-weight:800;margin-bottom:7px;">
                                                    {{ $label }}
                                                    @if ($isRequired)
                                                        <span style="color:#dc2626;">*</span>
                                                    @endif
                                                </label>

                                                @if ($type === 'textarea')
                                                    <textarea
                                                        name="{{ $fieldName }}"
                                                        @required($isRequired)
                                                        placeholder="{{ $placeholder }}"
                                                        rows="4"
                                                        style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                                    >{{ $oldValue }}</textarea>
                                                @elseif ($type === 'select')
                                                    <select
                                                        name="{{ $fieldName }}"
                                                        @required($isRequired)
                                                        style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;background:white;"
                                                    >
                                                        <option value="">Select option</option>

                                                        @foreach ($options as $optionKey => $optionValue)
                                                            @php
                                                                $optionLabel = is_array($optionValue)
                                                                    ? ($optionValue['label'] ?? $optionValue['value'] ?? '')
                                                                    : $optionValue;

                                                                $optionRealValue = is_array($optionValue)
                                                                    ? ($optionValue['value'] ?? $optionLabel)
                                                                    : $optionKey;

                                                                if (is_int($optionKey)) {
                                                                    $optionRealValue = $optionLabel;
                                                                }
                                                            @endphp

                                                            <option value="{{ $optionRealValue }}" @selected($oldValue == $optionRealValue)>
                                                                {{ $optionLabel }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @elseif ($type === 'radio')
                                                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                                        @foreach ($options as $optionKey => $optionValue)
                                                            @php
                                                                $optionLabel = is_array($optionValue)
                                                                    ? ($optionValue['label'] ?? $optionValue['value'] ?? '')
                                                                    : $optionValue;

                                                                $optionRealValue = is_array($optionValue)
                                                                    ? ($optionValue['value'] ?? $optionLabel)
                                                                    : $optionKey;

                                                                if (is_int($optionKey)) {
                                                                    $optionRealValue = $optionLabel;
                                                                }
                                                            @endphp

                                                            <label style="display:flex;align-items:center;gap:8px;border:1px solid #cbd5e1;border-radius:12px;padding:10px 12px;">
                                                                <input
                                                                    type="radio"
                                                                    name="{{ $fieldName }}"
                                                                    value="{{ $optionRealValue }}"
                                                                    @required($isRequired && $loop->first)
                                                                    @checked($oldValue == $optionRealValue)
                                                                >
                                                                <span>{{ $optionLabel }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                @elseif ($type === 'checkbox')
                                                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                                        @foreach ($options as $optionKey => $optionValue)
                                                            @php
                                                                $optionLabel = is_array($optionValue)
                                                                    ? ($optionValue['label'] ?? $optionValue['value'] ?? '')
                                                                    : $optionValue;

                                                                $optionRealValue = is_array($optionValue)
                                                                    ? ($optionValue['value'] ?? $optionLabel)
                                                                    : $optionKey;

                                                                if (is_int($optionKey)) {
                                                                    $optionRealValue = $optionLabel;
                                                                }

                                                                $oldArray = is_array($oldValue) ? $oldValue : [];
                                                            @endphp

                                                            <label style="display:flex;align-items:center;gap:8px;border:1px solid #cbd5e1;border-radius:12px;padding:10px 12px;">
                                                                <input
                                                                    type="checkbox"
                                                                    name="answers[{{ $field->id }}][]"
                                                                    value="{{ $optionRealValue }}"
                                                                    @checked(in_array($optionRealValue, $oldArray, true))
                                                                >
                                                                <span>{{ $optionLabel }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                @elseif ($type === 'date')
                                                    <input
                                                        type="date"
                                                        name="{{ $fieldName }}"
                                                        value="{{ $oldValue }}"
                                                        @required($isRequired)
                                                        style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                                    >
                                                @elseif ($type === 'number')
                                                    <input
                                                        type="number"
                                                        name="{{ $fieldName }}"
                                                        value="{{ $oldValue }}"
                                                        placeholder="{{ $placeholder }}"
                                                        @required($isRequired)
                                                        style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                                    >
                                                @elseif ($type === 'email')
                                                    <input
                                                        type="email"
                                                        name="{{ $fieldName }}"
                                                        value="{{ $oldValue }}"
                                                        placeholder="{{ $placeholder }}"
                                                        @required($isRequired)
                                                        style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                                    >
                                                @elseif ($type === 'phone')
                                                    <input
                                                        type="tel"
                                                        name="{{ $fieldName }}"
                                                        value="{{ $oldValue }}"
                                                        placeholder="{{ $placeholder ?: '255712345678' }}"
                                                        @required($isRequired)
                                                        style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                                    >
                                                @else
                                                    <input
                                                        type="text"
                                                        name="{{ $fieldName }}"
                                                        value="{{ $oldValue }}"
                                                        placeholder="{{ $placeholder }}"
                                                        @required($isRequired)
                                                        style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                                    >
                                                @endif

                                                @if ($helpText)
                                                    <div style="font-size:12px;color:#64748b;margin-top:6px;">
                                                        {{ $helpText }}
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if (($merchandiseItems ?? collect())->count())
                                <div style="
                                    margin-top: 22px;
                                    background: #ffffff;
                                    border: 1px solid #e2e8f0;
                                    border-radius: 20px;
                                    padding: 22px;
                                    box-shadow: 0 8px 22px rgba(15,23,42,0.06);
                                ">
                                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                                        <div>
                                            <h2 style="
                                                margin: 0;
                                                color: {{ $branding['primary_color'] }};
                                                font-size: 22px;
                                                font-weight: 900;
                                            ">
                                                Merchandise Order
                                            </h2>

                                            <p style="margin:7px 0 0;color:#64748b;font-size:14px;line-height:1.5;">
                                                Select the items you would like to order, including the preferred size, color and quantity.
                                                Payment instructions for paid items will be provided after registration.
                                            </p>
                                        </div>

                                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:10px 12px;font-size:12px;color:#475569;">
                                            Optional items may be skipped.
                                        </div>
                                    </div>

                                    <div style="display:grid;gap:16px;margin-top:18px;">
                                        @foreach ($merchandiseItems as $item)
                                            @php
                                                $oldSelected = $item->selection_type === 'required'
                                                    || (bool) old('merchandise.' . $item->id . '.selected', false);

                                                $oldVariantId = old('merchandise.' . $item->id . '.variant_id');
                                                $oldQuantity = old('merchandise.' . $item->id . '.quantity', 1);
                                                $maximumQuantity = max(1, (int) $item->maximum_per_attendee);
                                                $showItemImage = method_exists($item, 'shouldShowImage')
                                                    ? $item->shouldShowImage()
                                                    : ((bool) $item->show_image
                                                        && (bool) $event->show_merchandise_images
                                                        && filled($item->image_path));
                                            @endphp

                                            <div
                                                class="merchandise-card"
                                                data-merchandise-card
                                                data-required="{{ $item->selection_type === 'required' ? '1' : '0' }}"
                                                style="border:1px solid #e2e8f0;border-radius:18px;padding:18px;background:#f8fafc;"
                                            >
                                                <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;">
                                                    @if ($showItemImage)
                                                        <img
                                                            src="{{ asset('storage/' . $item->image_path) }}"
                                                            alt="{{ $item->name }}"
                                                            style="width:110px;height:110px;object-fit:cover;border-radius:16px;border:1px solid #e2e8f0;background:white;"
                                                        >
                                                    @endif

                                                    <div style="flex:1;min-width:240px;">
                                                        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;">
                                                            <div>
                                                                <h3 style="margin:0;font-size:18px;font-weight:900;color:#0f172a;">
                                                                    {{ $item->name }}
                                                                </h3>

                                                                @if (filled($item->description))
                                                                    <p style="margin:6px 0 0;color:#64748b;font-size:14px;line-height:1.5;">
                                                                        {{ $item->description }}
                                                                    </p>
                                                                @endif
                                                            </div>

                                                            <span style="
                                                                display:inline-flex;
                                                                align-items:center;
                                                                border-radius:999px;
                                                                padding:6px 10px;
                                                                font-size:11px;
                                                                font-weight:900;
                                                                background:{{ $item->selection_type === 'required' ? '#fee2e2' : '#e2e8f0' }};
                                                                color:{{ $item->selection_type === 'required' ? '#991b1b' : '#334155' }};
                                                            ">
                                                                {{ $item->selection_type === 'required' ? 'Required' : 'Optional' }}
                                                            </span>
                                                        </div>

                                                        @if ($item->selection_type === 'required')
                                                            <input
                                                                type="hidden"
                                                                name="merchandise[{{ $item->id }}][selected]"
                                                                value="1"
                                                            >
                                                        @else
                                                            <label style="display:flex;align-items:center;gap:10px;margin-top:14px;font-weight:800;cursor:pointer;">
                                                                <input
                                                                    type="checkbox"
                                                                    name="merchandise[{{ $item->id }}][selected]"
                                                                    value="1"
                                                                    data-merchandise-toggle
                                                                    @checked($oldSelected)
                                                                    style="width:18px;height:18px;"
                                                                >
                                                                <span>Add this item to my order</span>
                                                            </label>
                                                        @endif

                                                        <div
                                                            data-merchandise-fields
                                                            style="margin-top:16px;{{ $oldSelected ? '' : 'display:none;' }}"
                                                        >
                                                            @if ($item->activeVariants->isEmpty())
                                                                <div style="
                                                                    background:#fff7ed;
                                                                    color:#9a3412;
                                                                    border:1px solid #fed7aa;
                                                                    border-radius:12px;
                                                                    padding:12px;
                                                                    font-size:13px;
                                                                    font-weight:700;
                                                                ">
                                                                    This item is currently unavailable because no active variants have been configured.
                                                                </div>
                                                            @else
                                                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
                                                                    <div>
                                                                        <label style="display:block;font-weight:800;margin-bottom:7px;">
                                                                            Size / Color Variant
                                                                            <span style="color:#dc2626;">*</span>
                                                                        </label>

                                                                        <select
                                                                            name="merchandise[{{ $item->id }}][variant_id]"
                                                                            data-variant-select
                                                                            style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;background:white;"
                                                                        >
                                                                            <option value="">Select size and color</option>

                                                                            @foreach ($item->activeVariants as $variant)
                                                                                @php
                                                                                    $remaining = method_exists($variant, 'remainingQuantity')
                                                                                        ? $variant->remainingQuantity()
                                                                                        : max(0, (int) $variant->stock_quantity);

                                                                                    $variantName = method_exists($variant, 'displayName')
                                                                                        ? $variant->displayName()
                                                                                        : $variant->name;

                                                                                    $variantPrice = (float) ($variant->price ?? 0);
                                                                                    $variantCurrency = $variant->currency ?: 'TZS';
                                                                                @endphp

                                                                                <option
                                                                                    value="{{ $variant->id }}"
                                                                                    data-price="{{ $variantPrice }}"
                                                                                    data-currency="{{ $variantCurrency }}"
                                                                                    data-stock="{{ $remaining }}"
                                                                                    @selected((string) $oldVariantId === (string) $variant->id)
                                                                                    @disabled($remaining <= 0)
                                                                                >
                                                                                    {{ $variantName }}
                                                                                    — {{ $variantPrice > 0 ? $variantCurrency . ' ' . number_format($variantPrice, 2) : 'Free' }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>

                                                                        @error('merchandise.' . $item->id . '.variant_id')
                                                                            <div style="font-size:12px;color:#dc2626;margin-top:6px;font-weight:700;">
                                                                                {{ $message }}
                                                                            </div>
                                                                        @enderror
                                                                    </div>

                                                                    <div>
                                                                        <label style="display:block;font-weight:800;margin-bottom:7px;">
                                                                            Quantity
                                                                            <span style="color:#dc2626;">*</span>
                                                                        </label>

                                                                        <input
                                                                            type="number"
                                                                            name="merchandise[{{ $item->id }}][quantity]"
                                                                            value="{{ $oldQuantity }}"
                                                                            min="1"
                                                                            max="{{ $maximumQuantity }}"
                                                                            data-attendee-maximum="{{ $maximumQuantity }}"
                                                                            data-quantity-input
                                                                            style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                                                        >

                                                                        <div style="font-size:12px;color:#64748b;margin-top:6px;">
                                                                            Maximum allowed: {{ $maximumQuantity }}
                                                                        </div>

                                                                        @error('merchandise.' . $item->id . '.quantity')
                                                                            <div style="font-size:12px;color:#dc2626;margin-top:6px;font-weight:700;">
                                                                                {{ $message }}
                                                                            </div>
                                                                        @enderror
                                                                    </div>
                                                                </div>

                                                                <div style="margin-top:14px;background:white;border:1px solid #e2e8f0;border-radius:14px;padding:14px;">
                                                                    <div style="display:flex;justify-content:space-between;gap:12px;font-size:14px;color:#475569;">
                                                                        <span>Unit price</span>
                                                                        <strong data-unit-price>—</strong>
                                                                    </div>

                                                                    <div style="display:flex;justify-content:space-between;gap:12px;font-size:16px;margin-top:9px;color:#0f172a;">
                                                                        <span style="font-weight:900;">Total</span>
                                                                        <strong data-total-price>—</strong>
                                                                    </div>

                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <button
                                type="submit"
                                style="
                                    margin-top: 22px;
                                    width: 100%;
                                    border: none;
                                    border-radius: 16px;
                                    padding: 15px 18px;
                                    background: {{ $branding['button_color'] }};
                                    color: white;
                                    font-size: 16px;
                                    font-weight: 900;
                                    cursor: pointer;
                                "
                            >
                                {{ $isFull && $waitlistEnabled
                                    ? 'Join Waitlist'
                                    : (($merchandiseItems ?? collect())->count()
                                        ? 'Submit Registration and Order'
                                        : 'Submit Registration') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div style="text-align:center;color:#64748b;font-size:13px;margin-top:18px;">
                Powered by eLive Events
                @if ($branding['support_email'])
                    | Support: {{ $branding['support_email'] }}
                @endif
                @if ($branding['support_phone'])
                    | {{ $branding['support_phone'] }}
                @endif
            </div>
        </div>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const formatMoney = (amount, currency) => {
            const numericAmount = Number(amount || 0);

            if (numericAmount <= 0) {
                return 'Free';
            }

            return `${currency || 'TZS'} ${numericAmount.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}`;
        };

        document.querySelectorAll('[data-merchandise-card]').forEach((card) => {
            const required = card.dataset.required === '1';
            const toggle = card.querySelector('[data-merchandise-toggle]');
            const fields = card.querySelector('[data-merchandise-fields]');
            const variantSelect = card.querySelector('[data-variant-select]');
            const quantityInput = card.querySelector('[data-quantity-input]');
            const unitPrice = card.querySelector('[data-unit-price]');
            const totalPrice = card.querySelector('[data-total-price]');

            const refreshVisibility = () => {
                const selected = required || Boolean(toggle?.checked);

                fields.style.display = selected ? 'block' : 'none';

                if (variantSelect) {
                    variantSelect.disabled = !selected;
                    variantSelect.required = selected;
                }

                if (quantityInput) {
                    quantityInput.disabled = !selected;
                    quantityInput.required = selected;
                }

                if (!selected) {
                    if (variantSelect) {
                        variantSelect.value = '';
                    }

                    if (quantityInput) {
                        quantityInput.value = 1;
                        quantityInput.max = quantityInput.dataset.attendeeMaximum || 1;
                    }
                }

                refreshSummary();
            };

            const refreshSummary = () => {
                if (!variantSelect || !quantityInput || !unitPrice || !totalPrice) {
                    return;
                }

                const option = variantSelect.options[variantSelect.selectedIndex];
                const price = Number(option?.dataset.price || 0);
                const currency = option?.dataset.currency || 'TZS';
                const stock = Number(option?.dataset.stock || 0);
                const attendeeMaximum = Number(
                    quantityInput.dataset.attendeeMaximum || quantityInput.max || 1
                );

                const effectiveMaximum = option?.value
                    ? Math.max(1, Math.min(attendeeMaximum, Math.max(1, stock)))
                    : attendeeMaximum;

                quantityInput.max = effectiveMaximum;

                let quantity = Number(quantityInput.value || 1);
                quantity = Math.max(1, Math.min(quantity, effectiveMaximum));
                quantityInput.value = quantity;

                if (!option || !option.value) {
                    unitPrice.textContent = '—';
                    totalPrice.textContent = '—';
                    return;
                }

                unitPrice.textContent = formatMoney(price, currency);
                totalPrice.textContent = formatMoney(price * quantity, currency);
            };

            toggle?.addEventListener('change', refreshVisibility);
            variantSelect?.addEventListener('change', refreshSummary);
            quantityInput?.addEventListener('input', refreshSummary);
            quantityInput?.addEventListener('change', refreshSummary);

            refreshVisibility();
        });
    });
</script>

</body>
</html>