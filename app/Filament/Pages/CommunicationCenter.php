<?php

namespace App\Filament\Pages;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationTemplate;
use App\Models\Event;
use App\Services\CommunicationCampaignService;
use App\Services\SmsService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Throwable;
use UnitEnum;

class CommunicationCenter extends Page
{
    protected static ?string $navigationLabel = 'Communication Center';

    protected static ?string $title = 'Communication Center';

    protected static string|UnitEnum|null $navigationGroup = 'Communication';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.communication-center';

    /*
    |--------------------------------------------------------------------------
    | Campaign fields
    |--------------------------------------------------------------------------
    */

    public ?int $eventId = null;

    public ?int $templateId = null;

    public string $campaignName = '';

    public string $audience = 'all';

    public ?int $categoryId = null;

    public string $message = '';

    /*
    |--------------------------------------------------------------------------
    | Test SMS
    |--------------------------------------------------------------------------
    */

    public string $testPhone = '';

    /*
    |--------------------------------------------------------------------------
    | Audience preview
    |--------------------------------------------------------------------------
    */

    public array $preview = [
        'total' => 0,
        'valid' => 0,
        'invalid' => 0,
    ];

    /*
    |--------------------------------------------------------------------------
    | Mount
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        abort_unless(
            static::canAccess(),
            403
        );

        $firstEvent = $this->events()->first();

        if (! $firstEvent) {
            return;
        }

        $this->eventId = (int) $firstEvent->id;

        $this->campaignName =
            $firstEvent->name . ' SMS Campaign';

        $this->refreshPreview();
    }

    /*
    |--------------------------------------------------------------------------
    | Access control
    |--------------------------------------------------------------------------
    */

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return Event::query()
            ->accessibleBy($user)
            ->get()
            ->contains(
                fn (Event $event): bool =>
                    $user->canManageEventCommunication(
                        $event
                    )
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Livewire updates
    |--------------------------------------------------------------------------
    */

    public function updatedEventId(): void
    {
        $this->templateId = null;
        $this->categoryId = null;
        $this->message = '';

        if ($event = $this->selectedEvent()) {
            $this->campaignName =
                $event->name . ' SMS Campaign';
        } else {
            $this->campaignName = '';
        }

        $this->refreshPreview();
    }

    public function updatedAudience(): void
    {
        $this->refreshPreview();
    }

    public function updatedCategoryId(): void
    {
        $this->refreshPreview();
    }

    public function updatedTemplateId(): void
    {
        if (! $this->templateId) {
            return;
        }

        $template = $this->templates()
            ->firstWhere(
                'id',
                (int) $this->templateId
            );

        if (! $template) {
            return;
        }

        $this->message =
            (string) $template->body;

        if (blank($this->campaignName)) {
            $this->campaignName =
                $template->name;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SMS statistics
    |--------------------------------------------------------------------------
    */

    public function smsCharacterCount(): int
    {
        return mb_strlen(
            $this->message
        );
    }

    public function smsSegmentCount(): int
    {
        return app(
            SmsService::class
        )->segmentCount(
            $this->message
        );
    }

    public function estimatedSmsUnits(): int
    {
        return
            (int) ($this->preview['valid'] ?? 0)
            * $this->smsSegmentCount();
    }

    /*
    |--------------------------------------------------------------------------
    | Audience preview
    |--------------------------------------------------------------------------
    */

    public function refreshPreview(): void
    {
        $event = $this->selectedEvent();

        if (! $event) {
            $this->resetPreview();

            return;
        }

        $this->authorizeEvent(
            $event
        );

        $this->preview = app(
            CommunicationCampaignService::class
        )->preview(
            $event,
            $this->audience,
            $this->categoryId
        );
    }

    private function resetPreview(): void
    {
        $this->preview = [
            'total' => 0,
            'valid' => 0,
            'invalid' => 0,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Send test SMS
    |--------------------------------------------------------------------------
    */

    public function sendTestSms(): void
    {
        $this->validate([
            'eventId' => [
                'required',
                'integer',
            ],

            'testPhone' => [
                'required',
                'string',
                'max:30',
            ],

            'message' => [
                'required',
                'string',
                'max:5000',
            ],
        ]);

        $event =
            $this->selectedEvent();

        abort_unless(
            $event,
            404
        );

        $this->authorizeEvent(
            $event
        );

        $smsService = app(
            SmsService::class
        );

        $normalizedPhone =
            $smsService->normalizeRecipient(
                $this->testPhone
            );

        if (
            ! $smsService->isValidRecipient(
                $normalizedPhone
            )
        ) {
            Notification::make()
                ->title('Invalid test phone number')
                ->body(
                    'Enter a valid mobile number, for example 0768461644 or 255768461644.'
                )
                ->danger()
                ->send();

            return;
        }

        $testMessage =
            $this->renderTestMessage(
                $event
            );

        try {
            $result =
                $smsService->send(
                    $normalizedPhone,
                    $testMessage
                );

            $providerMessageId =
                $result['provider_message_id']
                ?? null;

            Notification::make()
                ->title('Test SMS sent')
                ->body(
                    $providerMessageId
                        ? "SMS accepted by the provider. Message ID: {$providerMessageId}"
                        : "A test SMS was sent to {$normalizedPhone}."
                )
                ->success()
                ->send();

            $this->testPhone =
                $normalizedPhone;
        } catch (Throwable $exception) {
            report(
                $exception
            );

            Notification::make()
                ->title('Test SMS failed')
                ->body(
                    $exception->getMessage()
                )
                ->danger()
                ->send();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Test placeholder rendering
    |--------------------------------------------------------------------------
    */

    private function renderTestMessage(
        Event $event
    ): string {
        $eventDate =
            $event->starts_at
                ? $event->starts_at->format(
                    'd M Y'
                )
                : '';

        $eventTime =
            $event->starts_at
                ? $event->starts_at->format(
                    'H:i'
                )
                : '';

        $replacements = [
            '#NAME#' =>
                'Test Attendee',

            '#PHONE#' =>
                $this->testPhone,

            '#EMAIL#' =>
                'test@example.com',

            '#ORGANIZATION#' =>
                'Test Organization',

            '#POSITION#' =>
                'Delegate',

            '#CATEGORY#' =>
                'Delegate',

            '#BADGE_NUMBER#' =>
                'ELV-TEST-001',

            '#EVENT_NAME#' =>
                (string) $event->name,

            '#EVENT_VENUE#' =>
                (string) ($event->venue ?? ''),

            '#EVENT_DATE#' =>
                $eventDate,

            '#EVENT_TIME#' =>
                $eventTime,
        ];

        return strtr(
            $this->message,
            $replacements
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Queue SMS campaign
    |--------------------------------------------------------------------------
    */

    public function queueCampaign(): void
    {
        $this->validate([
            'eventId' => [
                'required',
                'integer',
            ],

            'campaignName' => [
                'required',
                'string',
                'max:255',
            ],

            'audience' => [
                'required',
                'in:all,registered,confirmed,approved,pending_approval,waitlisted,checked_in,not_checked_in',
            ],

            'categoryId' => [
                'nullable',
                'integer',
            ],

            'templateId' => [
                'nullable',
                'integer',
            ],

            'message' => [
                'required',
                'string',
                'max:5000',
            ],
        ]);

        $event =
            $this->selectedEvent();

        abort_unless(
            $event,
            404
        );

        $this->authorizeEvent(
            $event
        );

        /*
         * Refresh immediately before queuing so the
         * campaign uses the latest available audience.
         */
        $this->refreshPreview();

        if (
            (int) ($this->preview['valid'] ?? 0)
            <= 0
        ) {
            Notification::make()
                ->title('No valid SMS recipients')
                ->body(
                    'The selected audience does not contain any attendees with a valid phone number.'
                )
                ->warning()
                ->send();

            return;
        }

        $template = null;

        if ($this->templateId) {
            $template =
                $this->templates()
                    ->firstWhere(
                        'id',
                        (int) $this->templateId
                    );

            abort_unless(
                $template,
                404
            );
        }

        try {
            $campaign = app(
                CommunicationCampaignService::class
            )->queueSmsCampaign(
                event: $event,
                name: $this->campaignName,
                message: $this->message,
                audience: $this->audience,
                categoryId: $this->categoryId,
                template: $template,
                createdBy: auth()->id()
            );

            Notification::make()
                ->title('SMS campaign queued')
                ->body(
                    "{$campaign->queued_count} SMS messages queued. "
                    . "{$campaign->failed_count} recipients skipped."
                )
                ->success()
                ->send();

            /*
             * Reset only message-related fields.
             * Keep the event and audience selected.
             */
            $this->message = '';
            $this->templateId = null;

            $this->campaignName =
                $event->name . ' SMS Campaign';

            $this->refreshPreview();
        } catch (Throwable $exception) {
            report(
                $exception
            );

            Notification::make()
                ->title(
                    'Campaign could not be queued'
                )
                ->body(
                    $exception->getMessage()
                )
                ->danger()
                ->send();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    */

    public function events(): Collection
    {
        $user = auth()->user();

        if (! $user) {
            return new Collection();
        }

        return Event::query()
            ->accessibleBy($user)
            ->with(
                'organization:id,name'
            )
            ->orderByDesc(
                'starts_at'
            )
            ->get()
            ->filter(
                fn (Event $event): bool =>
                    $user->canManageEventCommunication(
                        $event
                    )
            )
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Templates
    |--------------------------------------------------------------------------
    */

    public function templates(): Collection
    {
        $event =
            $this->selectedEvent();

        if (! $event) {
            return new Collection();
        }

        return CommunicationTemplate::query()
            ->forOrganization(
                (int) $event->organization_id
            )
            ->active()
            ->forChannel(
                CommunicationTemplate::CHANNEL_SMS
            )
            ->orderBy(
                'name'
            )
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */

    public function categories(): Collection
    {
        $event =
            $this->selectedEvent();

        if (! $event) {
            return new Collection();
        }

        return $event
            ->attendeeCategories()
            ->where(
                'is_active',
                true
            )
            ->orderBy(
                'name'
            )
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Recent campaigns
    |--------------------------------------------------------------------------
    */

    public function recentCampaigns(): Collection
    {
        $eventIds =
            $this->events()
                ->pluck('id');

        if ($eventIds->isEmpty()) {
            return new Collection();
        }

        return CommunicationCampaign::query()
            ->whereIn(
                'event_id',
                $eventIds
            )
            ->with(
                'event:id,name'
            )
            ->latest('id')
            ->limit(10)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Selected event
    |--------------------------------------------------------------------------
    */

    private function selectedEvent(): ?Event
    {
        if (! $this->eventId) {
            return null;
        }

        return $this->events()
            ->firstWhere(
                'id',
                (int) $this->eventId
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    private function authorizeEvent(
        Event $event
    ): void {
        $user =
            auth()->user();

        abort_unless(
            $user
            && $user->canManageEventCommunication(
                $event
            ),
            403
        );
    }
}