<x-filament-panels::page>
    @php($event = $this->event())
    @php($templates = $this->templates())
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold">{{ $event->name }}</h2>
            <p class="text-sm text-gray-500">Prepare certificate drafts. Publishing and issuance are not available yet.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-filament::button color="gray" tag="a" :href="\App\Filament\Resources\Events\EventResource::getUrl('certificate-settings', ['record' => $event])">Settings</x-filament::button>
            <x-filament::button wire:click="newDraft">New draft</x-filament::button>
        </div>
    </div>
    @if ($showForm)
        <x-filament::section>
            <x-slot name="heading">{{ $editingId ? 'Edit draft' : 'New certificate draft' }}</x-slot>
            <form wire:submit="saveDraft" class="space-y-4">
                <div class="grid gap-3 md:grid-cols-3">
                    <label class="block text-sm">Name
                        <input type="text" wire:model="name" maxlength="150" required class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900">
                        @error('name') <span class="text-danger-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm">Width (px)
                        <input type="number" wire:model="width" min="200" max="2400" required class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900">
                        @error('width') <span class="text-danger-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm">Height (px)
                        <input type="number" wire:model="height" min="200" max="2400" required class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900">
                        @error('height') <span class="text-danger-600">{{ $message }}</span> @enderror
                    </label>
                </div>
                <div class="flex justify-end gap-2">
                    <x-filament::button color="gray" type="button" wire:click="cancelEdit">Cancel</x-filament::button>
                    <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="saveDraft">Save draft</x-filament::button>
                </div>
            </form>
        </x-filament::section>
    @endif
    <x-filament::section>
        <x-slot name="heading">Templates</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr class="border-b border-gray-200 dark:border-gray-700"><th class="p-3">Name</th><th class="p-3">Dimensions</th><th class="p-3">Status</th><th class="p-3">Actions</th></tr></thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr wire:key="certificate-template-{{ $template->id }}" class="border-b border-gray-100 dark:border-gray-800">
                            <td class="p-3 font-medium">{{ $template->name }}</td>
                            <td class="p-3 whitespace-nowrap">{{ $template->width }} × {{ $template->height }}</td>
                            <td class="p-3">{{ $template->is_active ? 'Active' : 'Draft' }}</td>
                            <td class="p-3">
                                <div class="flex flex-wrap gap-2">
                                    @if (! $template->is_active && ! $template->certificates_count)
                                        <x-filament::button size="sm" tag="a" :href="\App\Filament\Resources\Events\EventResource::getUrl('certificate-designer', ['record' => $event, 'template' => $template->id])">Design</x-filament::button>
                                        <x-filament::button size="sm" color="gray" wire:click="editDraft({{ $template->id }})">Edit</x-filament::button>
                                    @endif
                                    @if (! $template->assets_count && empty($template->definition))
                                        <x-filament::button size="sm" color="gray" wire:click="duplicateDraft({{ $template->id }})" wire:loading.attr="disabled" wire:target="duplicateDraft">Duplicate</x-filament::button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-6 text-center text-gray-500">No certificate templates yet. Create your first draft.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $templates->links() }}</div>
    </x-filament::section>
</x-filament-panels::page>
