@php
    $days = $attendee->eventDays ?? collect();
    $orders = $attendee->merchandiseSelections ?? collect();
    $answers = $attendee->registrationAnswers ?? collect();

    $orderCurrency = $orders
        ->pluck('currency')
        ->filter()
        ->first() ?: 'TZS';

    $orderTotal = (float) $orders->sum(
        fn ($selection) => (float) ($selection->total_price ?? 0)
    );
@endphp

<div style="display:grid;gap:20px;">
    <section style="border:1px solid #e5e7eb;border-radius:16px;padding:18px;background:#ffffff;">
        <h3 style="margin:0 0 14px;font-size:17px;font-weight:800;color:#111827;">
            Attendee Information
        </h3>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
            @foreach ([
                'Full Name' => $attendee->full_name,
                'Phone' => $attendee->phone,
                'Email' => $attendee->email,
                'Organization' => $attendee->organization_name,
                'Position' => $attendee->position,
                'Category' => $attendee->category?->name,
                'Badge Type' => $attendee->badgeType?->name,
                'Badge Number' => $attendee->badge_number,
                'Status' => $attendee->status,
                'Registration Source' => $attendee->registration_source,
                'Registered At' => $attendee->registered_at?->format('d M Y, H:i'),
                'Checked In At' => $attendee->checked_in_at?->format('d M Y, H:i'),
            ] as $label => $value)
                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:12px;">
                    <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#6b7280;">
                        {{ $label }}
                    </div>
                    <div style="margin-top:5px;font-size:14px;font-weight:700;color:#111827;word-break:break-word;">
                        {{ filled($value) ? $value : '—' }}
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section style="border:1px solid #e5e7eb;border-radius:16px;padding:18px;background:#ffffff;">
        <h3 style="margin:0 0 14px;font-size:17px;font-weight:800;color:#111827;">
            Selected Attendance Days
        </h3>

        @if ($days->isEmpty())
            <div style="color:#6b7280;font-size:14px;">No attendance days selected.</div>
        @else
            <div style="display:grid;gap:10px;">
                @foreach ($days as $day)
                    <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:12px;">
                        <div>
                            <div style="font-weight:800;color:#111827;">{{ $day->name }}</div>
                            <div style="margin-top:4px;font-size:13px;color:#6b7280;">
                                {{ $day->event_date?->format('d M Y') ?? 'Date not set' }}
                                @if ($day->starts_at)
                                    — {{ $day->starts_at?->format('H:i') }}
                                @endif
                                @if ($day->ends_at)
                                    to {{ $day->ends_at?->format('H:i') }}
                                @endif
                            </div>
                        </div>

                        <div style="font-size:13px;font-weight:700;color:#374151;">
                            {{ $day->venue_name ?: 'Main event venue' }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section style="border:1px solid #e5e7eb;border-radius:16px;padding:18px;background:#ffffff;">
        <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap;">
            <h3 style="margin:0;font-size:17px;font-weight:800;color:#111827;">
                Merchandise Order
            </h3>

            <div style="background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe;border-radius:999px;padding:7px 11px;font-size:12px;font-weight:800;">
                Total:
                {{ $orderTotal > 0
                    ? $orderCurrency . ' ' . number_format($orderTotal, 2)
                    : ($orders->isEmpty() ? 'No order' : 'Free') }}
            </div>
        </div>

        @if ($orders->isEmpty())
            <div style="margin-top:14px;color:#6b7280;font-size:14px;">
                No merchandise order submitted.
            </div>
        @else
            <div style="margin-top:14px;overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;min-width:760px;">
                    <thead>
                        <tr style="background:#f9fafb;">
                            @foreach ([
                                'Item',
                                'Variant',
                                'Qty',
                                'Unit Price',
                                'Total',
                                'Order Status',
                                'Payment',
                            ] as $heading)
                                <th style="padding:10px;border:1px solid #e5e7eb;text-align:left;font-size:12px;color:#4b5563;">
                                    {{ $heading }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $selection)
                            <tr>
                                <td style="padding:10px;border:1px solid #e5e7eb;font-size:13px;font-weight:700;">
                                    {{ $selection->merchandise?->name ?: '—' }}
                                </td>
                                <td style="padding:10px;border:1px solid #e5e7eb;font-size:13px;">
                                    {{ method_exists($selection->variant, 'displayName')
                                        ? $selection->variant->displayName()
                                        : ($selection->variant?->name ?: '—') }}
                                </td>
                                <td style="padding:10px;border:1px solid #e5e7eb;font-size:13px;">
                                    {{ $selection->quantity }}
                                </td>
                                <td style="padding:10px;border:1px solid #e5e7eb;font-size:13px;">
                                    {{ $selection->currency ?: 'TZS' }}
                                    {{ number_format((float) ($selection->unit_price ?? 0), 2) }}
                                </td>
                                <td style="padding:10px;border:1px solid #e5e7eb;font-size:13px;font-weight:800;">
                                    {{ $selection->currency ?: 'TZS' }}
                                    {{ number_format((float) ($selection->total_price ?? 0), 2) }}
                                </td>
                                <td style="padding:10px;border:1px solid #e5e7eb;font-size:13px;">
                                    {{ ucfirst(str_replace('_', ' ', $selection->status ?: 'unknown')) }}
                                </td>
                                <td style="padding:10px;border:1px solid #e5e7eb;font-size:13px;">
                                    {{ ucfirst(str_replace('_', ' ', $selection->payment_status ?: 'unknown')) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section style="border:1px solid #e5e7eb;border-radius:16px;padding:18px;background:#ffffff;">
        <h3 style="margin:0 0 14px;font-size:17px;font-weight:800;color:#111827;">
            Additional Registration Answers
        </h3>

        @if ($answers->isEmpty())
            <div style="color:#6b7280;font-size:14px;">
                No additional answers submitted.
            </div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
                @foreach ($answers as $answer)
                    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:12px;">
                        <div style="font-size:12px;font-weight:800;color:#4b5563;">
                            {{ $answer->registrationField?->label
                                ?? $answer->registrationField?->name
                                ?? 'Registration Field' }}
                        </div>
                        <div style="margin-top:6px;font-size:14px;color:#111827;white-space:pre-wrap;word-break:break-word;">
                            {{ $answer->answer ?: '—' }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
