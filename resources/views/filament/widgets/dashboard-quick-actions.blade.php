<x-filament-widgets::widget>
    <x-filament::section heading="Quick actions">
        <div class="elive-quick-actions-grid elive-quick-actions-compact">
            @php
                $links = [
                    [\App\Filament\Resources\TicketOrders\TicketOrderResource::class, 'Ticket orders', 'canViewAny', 'heroicon-o-ticket'],
                    [\App\Filament\Pages\OrganizerSalesDashboard::class, 'Sales', 'canAccess', 'heroicon-o-chart-bar'],
                    [\App\Filament\Pages\CheckInStation::class, 'Check-in', 'canAccess', 'heroicon-o-qr-code'],
                    [\App\Filament\Pages\CommunicationCenter::class, 'Communications', 'canAccess', 'heroicon-o-envelope'],
                ];
            @endphp

            @foreach ($links as [$page, $label, $permission, $icon])
                @if ($page::$permission())
                    <x-filament::button tag="a" color="gray" outlined :icon="$icon" :href="$page::getUrl()" class="elive-dashboard-secondary">
                        {{ $label }}
                    </x-filament::button>
                @endif
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
