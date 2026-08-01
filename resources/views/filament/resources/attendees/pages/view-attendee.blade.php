<x-filament-panels::page>
    @php
        $attendee = $this->getRecord();

        $days = $attendee->eventDays ?? collect();
        $orders = $attendee->merchandiseSelections ?? collect();
        $answers = $attendee->registrationAnswers ?? collect();
        $checkIns = $attendee->checkIns ?? collect();

        $orderCurrency = $orders
            ->pluck('currency')
            ->filter()
            ->first() ?: 'TZS';

        $orderTotal = (float) $orders->sum(
            fn ($selection) =>
                (float) ($selection->total_price ?? 0)
        );
    @endphp

    <div class="grid gap-6">
        <x-filament::section>
            <x-slot name="heading">Attendee Overview</x-slot>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
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
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {{ $label }}
                        </div>

                        <div class="mt-1 break-words text-sm font-semibold text-gray-950 dark:text-white">
                            {{ filled($value)
                                ? ucfirst(str_replace('_', ' ', (string) $value))
                                : '—' }}
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Selected Attendance Days</x-slot>

            @if ($days->isEmpty())
                <p class="text-sm text-gray-500">No attendance days selected.</p>
            @else
                <div class="grid gap-3">
                    @foreach ($days as $day)
                        <div class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div>
                                <div class="font-semibold text-gray-950 dark:text-white">
                                    {{ $day->name }}
                                </div>
                                <div class="mt-1 text-sm text-gray-500">
                                    {{ $day->event_date?->format('d M Y') ?? 'Date not set' }}
                                    @if ($day->starts_at)
                                        • {{ $day->starts_at?->format('H:i') }}
                                    @endif
                                    @if ($day->ends_at)
                                        – {{ $day->ends_at?->format('H:i') }}
                                    @endif
                                </div>
                            </div>
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-300">
                                {{ $day->venue_name ?: 'Main event venue' }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Merchandise Orders</x-slot>
            <x-slot name="description">
                Total:
                {{ $orders->isEmpty()
                    ? 'No order'
                    : ($orderTotal > 0
                        ? $orderCurrency . ' ' . number_format($orderTotal, 2)
                        : 'Free') }}
            </x-slot>

            @if ($orders->isEmpty())
                <p class="text-sm text-gray-500">No merchandise order submitted.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[850px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                @foreach (['Item','Variant','Quantity','Unit Price','Total','Order Status','Payment'] as $heading)
                                    <th class="px-3 py-3 font-semibold text-gray-700 dark:text-gray-200">
                                        {{ $heading }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $selection)
                                <tr class="border-b border-gray-100 dark:border-white/5">
                                    <td class="px-3 py-3 font-semibold">{{ $selection->merchandise?->name ?: '—' }}</td>
                                    <td class="px-3 py-3">
                                        {{ method_exists($selection->variant, 'displayName')
                                            ? $selection->variant->displayName()
                                            : ($selection->variant?->name ?: '—') }}
                                    </td>
                                    <td class="px-3 py-3">{{ $selection->quantity }}</td>
                                    <td class="px-3 py-3">
                                        {{ $selection->currency ?: 'TZS' }}
                                        {{ number_format((float) ($selection->unit_price ?? 0), 2) }}
                                    </td>
                                    <td class="px-3 py-3 font-semibold">
                                        {{ $selection->currency ?: 'TZS' }}
                                        {{ number_format((float) ($selection->total_price ?? 0), 2) }}
                                    </td>
                                    <td class="px-3 py-3">
                                        {{ ucfirst(str_replace('_', ' ', $selection->status ?: 'unknown')) }}
                                    </td>
                                    <td class="px-3 py-3">
                                        {{ ucfirst(str_replace('_', ' ', $selection->payment_status ?: 'unknown')) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Additional Registration Answers</x-slot>

            @if ($answers->isEmpty())
                <p class="text-sm text-gray-500">No additional answers submitted.</p>
            @else
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($answers as $answer)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                {{ $answer->registrationField?->label
                                    ?? $answer->registrationField?->name
                                    ?? 'Registration Field' }}
                            </div>
                            <div class="mt-2 whitespace-pre-wrap break-words text-sm text-gray-950 dark:text-white">
                                {{ filled($answer->answer) ? $answer->answer : '—' }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Check-in History</x-slot>

            @if ($checkIns->isEmpty())
                <p class="text-sm text-gray-500">No check-in records found.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                @foreach (['Point','Method','Checked In At','Device','Notes'] as $heading)
                                    <th class="px-3 py-3 font-semibold text-gray-700 dark:text-gray-200">
                                        {{ $heading }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($checkIns as $checkIn)
                                <tr class="border-b border-gray-100 dark:border-white/5">
                                    <td class="px-3 py-3">{{ $checkIn->checkInPoint?->name ?: '—' }}</td>
                                    <td class="px-3 py-3">{{ ucfirst(str_replace('_', ' ', $checkIn->method ?: 'unknown')) }}</td>
                                    <td class="px-3 py-3">{{ $checkIn->checked_in_at?->format('d M Y, H:i:s') ?? '—' }}</td>
                                    <td class="px-3 py-3">{{ $checkIn->device_name ?: '—' }}</td>
                                    <td class="px-3 py-3">{{ $checkIn->note ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
