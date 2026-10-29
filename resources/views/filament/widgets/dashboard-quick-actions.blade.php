<x-filament-widgets::widget>
    <x-filament::section heading="Quick actions" description="Open the tools available for your role.">
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
            @php
                $links = [
                    [\App\Filament\Resources\Events\EventResource::class, 'Events', 'canViewAny'],
                    [\App\Filament\Resources\TicketOrders\TicketOrderResource::class, 'Ticket Orders', 'canViewAny'],
                    [\App\Filament\Pages\OrganizerSalesDashboard::class, 'Sales Dashboard', 'canAccess'],
                    [\App\Filament\Pages\OrganizerFinanceOverview::class, 'My Finance', 'canAccess'],
                    [\App\Filament\Pages\AttendanceDashboard::class, 'Attendance', 'canAccess'],
                    [\App\Filament\Pages\CheckInStation::class, 'Check-in Station', 'canAccess'],
                    [\App\Filament\Pages\CommunicationCenter::class, 'Communications', 'canAccess'],
                ];
            @endphp

            @foreach ($links as [$page, $label, $permission])
                @if ($page::$permission())
                    <a href="{{ $page::getUrl() }}"
                       class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-800 shadow-sm transition hover:border-primary-400 hover:text-primary-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                        {{ $label }}
                    </a>
                @endif
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
