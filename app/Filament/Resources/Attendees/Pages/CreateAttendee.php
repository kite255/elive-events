<?php

namespace App\Filament\Resources\Attendees\Pages;

use App\Filament\Resources\Attendees\AttendeeResource;
use App\Models\Event;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class CreateAttendee extends CreateRecord
{
    protected static string $resource = AttendeeResource::class;

    #[Locked]
    public ?int $workspaceEventId = null;

    public function mount(): void
    {
        if (request()->has('event_id')) {
            $id = request()->query('event_id');
            abort_unless(is_scalar($id) && ctype_digit((string) $id), 404);
            $this->workspaceEventId = (int) $id;
            $this->authorizedEvent($this->workspaceEventId);
        }

        parent::mount();
    }

    private function authorizedEvent(int $id): Event
    {
        $user = auth()->user();
        abort_unless($user instanceof User && ! $user->is_gate_staff, 403);
        $event = AttendeeResource::authorizedAttendeeEvents($user)->whereKey($id)->firstOrFail();
        abort_unless($event->canManageRegistrationBy($user), 403);

        return $event;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $event = $this->authorizedEvent($this->workspaceEventId ?? (int) ($data['event_id'] ?? 0));
        $data['event_id'] = $event->id;

        foreach ([
            'category_id' => $event->attendeeCategories(),
            'badge_type_id' => $event->badgeTypes()->where('is_active', true),
        ] as $field => $query) {
            if (filled($data[$field] ?? null) && ! $query->whereKey($data[$field])->exists()) {
                throw ValidationException::withMessages(['data.'.$field => 'Select a valid option for this event.']);
            }
        }

        $days = $this->data['eventDays'] ?? [];
        if (! is_array($days) || collect($days)->contains(fn ($id) => ! is_scalar($id) || ! ctype_digit((string) $id))
            || $event->days()->where('status', 'active')->whereIn('id', $days)->count() !== count(array_unique($days))) {
            throw ValidationException::withMessages(['data.eventDays' => 'Select valid active attendance days for this event.']);
        }

        unset($data['badge_path']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return AttendeeResource::getUrl('index', $this->workspaceEventId === null ? [] : ['event_id' => $this->workspaceEventId]);
    }
}
