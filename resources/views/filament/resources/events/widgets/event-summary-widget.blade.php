<x-filament-widgets::widget>
    @if ($event)
        <div style="
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        ">
            <div style="
                display: flex;
                justify-content: space-between;
                gap: 20px;
                align-items: flex-start;
                flex-wrap: wrap;
                margin-bottom: 22px;
            ">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <h2 style="
                            font-size: 24px;
                            font-weight: 800;
                            color: #0f172a;
                            margin: 0;
                        ">
                            {{ $event->name }}
                        </h2>

                        <span style="
                            background: #eff6ff;
                            color: #1d4ed8;
                            border: 1px solid #bfdbfe;
                            border-radius: 999px;
                            padding: 4px 10px;
                            font-size: 12px;
                            font-weight: 700;
                        ">
                            {{ ucfirst($event->event_type ?: 'Event') }}
                        </span>

                        <span style="
                            background: {{ $event->status === 'draft' ? '#fefce8' : '#ecfdf5' }};
                            color: {{ $event->status === 'draft' ? '#a16207' : '#047857' }};
                            border: 1px solid {{ $event->status === 'draft' ? '#fde68a' : '#a7f3d0' }};
                            border-radius: 999px;
                            padding: 4px 10px;
                            font-size: 12px;
                            font-weight: 700;
                        ">
                            {{ ucfirst($event->status ?? 'Draft') }}
                        </span>
                    </div>

                    <p style="
                        margin-top: 8px;
                        margin-bottom: 0;
                        color: #64748b;
                        font-size: 14px;
                        max-width: 760px;
                    ">
                        {{ $event->description ?: 'No event description added yet.' }}
                    </p>
                </div>

                <div style="
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 14px;
                    padding: 12px 16px;
                    min-width: 150px;
                ">
                    <div style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase;">
                        Capacity
                    </div>
                    <div style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 4px;">
                        {{ $event->capacity ? number_format($event->capacity) : 'Unlimited' }}
                    </div>
                </div>
            </div>

            <div style="
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 14px;
            ">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px;">
                    <div style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase;">
                        Organization
                    </div>
                    <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-top: 6px;">
                        {{ $event->organization?->name ?? 'No organization' }}
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px;">
                    <div style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase;">
                        Venue
                    </div>
                    <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-top: 6px;">
                        {{ $event->venue ?: 'No venue added' }}
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px;">
                    <div style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase;">
                        Starts
                    </div>
                    <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-top: 6px;">
                        {{ $event->starts_at?->format('M d, Y H:i') ?? 'No start date' }}
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px;">
                    <div style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase;">
                        Ends
                    </div>
                    <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-top: 6px;">
                        {{ $event->ends_at?->format('M d, Y H:i') ?? 'No end date' }}
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>