<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\User;
use App\Services\EventCertificateAccess;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Locked;

class EventCertificateSettings extends Page
{
    protected static string $resource = EventResource::class;
    protected string $view = 'filament.resources.events.pages.event-certificate-settings';
    protected static ?string $title = 'Certificate settings';

    #[Locked]
    public int $eventId;

    public bool $enabled = false;
    public string $eligibilityRule = 'checked_in';

    public function mount(int|string $record): void
    {
        abort_unless(ctype_digit((string) $record), 404);
        $this->eventId = (int) $record;
        $settings = $this->event()->certificateSettings()->first();
        $this->enabled = (bool) $settings?->enabled;
        $this->eligibilityRule = $settings?->eligibility_rule ?? 'checked_in';
    }

    protected function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function event(): Event
    {
        return app(EventCertificateAccess::class)->authorize($this->user(), $this->eventId);
    }

    public function saveSettings(): void
    {
        app(EventCertificateAccess::class)->configure(
            $this->user(), $this->eventId, $this->enabled, $this->eligibilityRule
        );

        Notification::make()->title('Certificate settings saved')->success()->send();
    }
}
