<x-filament-panels::page>
    <p class="text-sm text-gray-500">Recorded event performance. Ticket and public shop amounts are separate; recorded revenue is not proof of settlement. Session-only check-ins are excluded from the event attendance rate.</p>
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($this->sections() as $section)
            <x-filament::section>
                <x-slot name="heading">{{ $section['title'] }}</x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="text-left">
                            @foreach ($section['headings'] as $heading)
                                <th class="p-2">{{ $heading }}</th>
                            @endforeach
                        </tr></thead>
                        <tbody>
                            @forelse ($section['rows'] as $row)
                                <tr class="border-t border-gray-200 dark:border-gray-700">
                                    @foreach ($row as $value)
                                        <td class="p-2">{{ $value ?? '—' }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($section['headings']) }}" class="p-2">No records.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
