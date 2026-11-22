@vite('resources/js/ticket-scanner.js')

<x-filament-panels::page>
    @include('filament.pages.partials.ticket-scanner-workspace', [
        'scanUrl' => $this->getScanUrl(),
        'assignedEvent' => $this->getAssignedEventLabel(),
        'withAttendance' => true,
    ])
</x-filament-panels::page>
