<?php

namespace App\Filament\Pages;

use BackedEnum;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationTemplate;
use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\CommunicationCampaignService;
use App\Services\SmsService;
use App\Services\Tickets\TicketAccessMessageFactory;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Throwable;
use UnitEnum;

class CommunicationCenter extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Communication Center';

    protected static ?string $title = 'Communication Center';

    protected static string|UnitEnum|null $navigationGroup = 'Communications';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.communication-center';

    /*
    |--------------------------------------------------------------------------
    | Campaign fields
    |--------------------------------------------------------------------------
    */

    public ?int $eventId = null;

    public ?int $templateId = null;

    public string $channel =
        CommunicationTemplate::CHANNEL_EMAIL;

    public string $campaignName = '';

    public string $audience = 'all';

    public ?int $categoryId = null;

    public ?string $subject = null;

    public string $message = '';

    public $emailPoster = null;

    public ?string $emailPosterPath = null;

    public string $emailActionUrl = '';

    public string $emailActionLabel = '';

    /*
    |--------------------------------------------------------------------------
    | Test recipients
    |--------------------------------------------------------------------------
    */

    public string $testPhone = '';

    public string $testEmail = '';

    /*
    |--------------------------------------------------------------------------
    | Audience options
    |--------------------------------------------------------------------------
    */

    #[Locked]
    public array $audienceOptions = [
        'all' => 'All Attendees',
        CommunicationCampaignService::PAID_TICKET_BUYERS => 'Paid Ticket Buyers',
        'registered' => 'Registered',
        'confirmed' => 'Confirmed',
        'approved' => 'Approved',
        'pending_approval' => 'Pending Approval',
        'waitlisted' => 'Waitlisted',
        'checked_in' => 'Checked In',
        'not_checked_in' => 'Not Checked In',
    ];

    public function getSelectedEventProperty(): ?Event
    {
        return $this->selectedEvent();
    }

    public function getSelectedTemplateProperty(): ?CommunicationTemplate
    {
        if (! $this->templateId) {
            return null;
        }

        return $this->templates()
            ->firstWhere(
                'id',
                (int) $this->templateId
            );
    }

    public function getSmsStatsProperty(): array
    {
        return [
            'characters' => $this->smsCharacterCount(),
            'segments' => $this->smsSegmentCount(),
            'estimated_units' => $this->estimatedSmsUnits(),
        ];
    }

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
            $this->defaultCampaignName(
                $firstEvent
            );

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

        if (! $user instanceof User) {
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

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
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
        $this->subject = null;
        $this->resetEmailArtwork();

        if ($event = $this->selectedEvent()) {
            $this->campaignName =
                $this->defaultCampaignName(
                    $event
                );
        } else {
            $this->campaignName = '';
        }

        $this->refreshPreview();
    }

    public function updatedChannel(): void
    {
        $this->templateId = null;
        $this->subject = null;
        $this->message = '';
        $this->resetEmailArtwork();

        if ($event = $this->selectedEvent()) {
            $this->campaignName =
                $this->defaultCampaignName(
                    $event
                );
        }

        $this->refreshPreview();
    }

    public function updatedAudience(): void
    {
        $this->categoryId = null;
        $this->refreshPreview();
    }

    public function updatedMessage(): void
    {
        if ($this->isTicketBuyerAudience() && $this->isSmsChannel()) {
            $this->refreshPreview();
        }
    }

    public function isTicketBuyerAudience(): bool
    {
        return $this->audience === CommunicationCampaignService::PAID_TICKET_BUYERS;
    }

    public function ticketBuyerPlaceholders(): array
    {
        return [
            '#PURCHASER_NAME#', '#TICKET_LINK#', '#ORDER_REFERENCE#', '#TICKET_COUNT#', '#TICKET_TYPE#',
            '#EVENT_NAME#', '#EVENT_VENUE#', '#EVENT_DATE#', '#EVENT_TIME#',
        ];
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

        $this->subject =
            $template->isEmail()
                ? (string) ($template->subject ?? '')
                : null;

        $this->updatedMessage();

        $event =
            $this->selectedEvent();

        if ($event) {
            $this->campaignName =
                $event->name
                . ' - '
                . (
                    $template->name
                    ?: $this->channelLabel()
                );
        }
    }

    public function resetCampaign(): void
    {
        $event = $this->selectedEvent();

        $this->templateId = null;
        $this->audience = 'all';
        $this->categoryId = null;
        $this->subject = null;
        $this->message = '';
        $this->testPhone = '';
        $this->testEmail = '';
        $this->resetEmailArtwork();

        $this->campaignName = $event
            ? $this->defaultCampaignName(
                $event
            )
            : '';

        $this->resetValidation();
        $this->refreshPreview();
    }

    private function resetEmailArtwork(): void
    {
        $this->emailPoster = null;
        $this->emailPosterPath = null;
        $this->emailActionUrl = '';
        $this->emailActionLabel = '';
    }

    private function saveEmailPoster(): void
    {
        if ($this->emailPoster !== null) {
            $this->emailPosterPath = $this->emailPoster->store('event-campaign-posters', 'public');
            $this->emailPoster = null;
        }
    }

    private function emailPosterUrl(): ?string
    {
        if (! $this->emailPosterPath || ! Storage::disk('public')->exists($this->emailPosterPath)) {
            return null;
        }

        return url(Storage::disk('public')->url($this->emailPosterPath));
    }

    public function loadRevivedWayThankYou(): void
    {
        $event = $this->selectedEvent();
        abort_unless($event && str_contains(strtolower($event->name), 'revived way'), 403);
        $this->authorizeEvent($event);

        $this->channel = CommunicationTemplate::CHANNEL_EMAIL;
        $this->audience = CommunicationCampaignService::PAID_TICKET_BUYERS;
        $this->templateId = null;
        $this->categoryId = null;
        $this->campaignName = 'The Revived Way - Thank You and Spotify Release';
        $this->subject = 'Thank You for Being Part of The Revived Way | Album Now on Spotify';
        $this->message = "Dear #PURCHASER_NAME#,\n\nOn behalf of Revived Music Ministries, thank you for supporting The Revived Way Album Launch Concert. Your presence, prayers, encouragement and support made the celebration special.\n\nThe Revived Way album is now on Spotify! Stream, share and be blessed through the music.\n\nWe pray that these songs continue to inspire and encourage you. We look forward to worshipping with you again!\n\nBlessings,\nRevived Music Ministries";
        $this->emailActionUrl = 'https://open.spotify.com/album/13nND3Cb2ZIek3XEPdH8Jr';
        $this->emailActionLabel = 'Listen on Spotify';
        $this->refreshPreview();
    }

    /*
    |--------------------------------------------------------------------------
    | SMS statistics
    |--------------------------------------------------------------------------
    */

    public function smsCharacterCount(): int
    {
        if (
            $this->channel
            !== CommunicationTemplate::CHANNEL_SMS
        ) {
            return 0;
        }

        return mb_strlen(
            $this->message
        );
    }

    public function smsSegmentCount(): int
    {
        if (
            $this->channel
            !== CommunicationTemplate::CHANNEL_SMS
        ) {
            return 0;
        }

        if ($this->isTicketBuyerAudience()) {
            return (int) ($this->preview['max_sms_segments'] ?? 0);
        }

        return app(
            SmsService::class
        )->segmentCount(
            $this->message
        );
    }

    public function estimatedSmsUnits(): int
    {
        if ($this->isTicketBuyerAudience()) {
            return (int) ($this->preview['sms_segments'] ?? 0);
        }

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
            $this->isTicketBuyerAudience() ? null : $this->categoryId,
            $this->channel,
            $this->message,
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

        $testMessage = $this->renderTestMessage($event);
        if ($this->isTicketBuyerAudience()) {
            $testMessage = 'TEST: '.$testMessage;
        }

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
    | Send Test Email
    |--------------------------------------------------------------------------
    */

    public function sendTestEmail(): void
    {
        $this->validate([
            'emailPoster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'emailActionUrl' => ['nullable', 'url', 'starts_with:https://', 'max:2048'],
            'emailActionLabel' => ['nullable', 'string', 'max:80'],
            'eventId' => [
                'required',
                'integer',
            ],

            'testEmail' => [
                'required',
                'email',
                'max:255',
            ],

            'subject' => [
                'required',
                'string',
                'max:255',
            ],

            'message' => [
                'required',
                'string',
                'max:10000',
            ],
        ]);

        if (
            $this->channel
            !== CommunicationTemplate::CHANNEL_EMAIL
        ) {
            Notification::make()
                ->title('Email channel is not selected')
                ->warning()
                ->send();

            return;
        }

        $event =
            $this->selectedEvent();

        abort_unless(
            $event,
            404
        );

        $this->authorizeEvent(
            $event
        );

        $this->saveEmailPoster();

        $renderedMessage =
            $this->renderTestMessage(
                $event
            );

        $this->testEmail =
            strtolower(
                trim(
                    $this->testEmail
                )
            );

        $renderedSubject =
            strtr(
                trim(
                    (string) $this->subject
                ),
                [
                    '#EVENT_NAME#' =>
                        (string) $event->name,

                    '#EVENT_VENUE#' =>
                        (string) ($event->venue ?? ''),

                    '#NAME#' =>
                        'Test Attendee',
                ]
            );

        if ($this->isTicketBuyerAudience()) {
            $renderedSubject = '[TEST] '.$this->renderTicketBuyerTest((string) $this->subject, $event);
            if (str_contains($this->message, '#TICKET_LINK#')) {
                $renderedMessage = "Test only. The link opens the public ticket finder.\n\n".$renderedMessage;
            }
        }

        try {
            Mail::send(
                'emails.elive',
                [
                    'subject' =>
                        $renderedSubject,

                    'communicationLog' =>
                        null,

                    'attendee' =>
                        null,

                    'ticketCampaignUrl' => $this->isTicketBuyerAudience()
                        ? route('public.ticket-recovery.show')
                        : null,

                    'event' =>
                        $event,

                    'messageBody' =>
                        $renderedMessage,

                    'emailLabel' =>
                        $this->selectedTemplate?->name
                        ?: 'Event Communication',

                    'alertTitle' =>
                        null,

                    'alertMessage' =>
                        null,

                    'campaignPosterUrl' => $this->emailPosterUrl(),
                    'actionUrl' => $this->emailActionUrl ?: null,
                    'actionLabel' => $this->emailActionLabel ?: null,
                    'actionButtonColor' => str_starts_with($this->emailActionUrl, 'https://open.spotify.com/') ? '#1DB954' : '#007AB2',
                    'actionPlayIcon' => str_starts_with($this->emailActionUrl, 'https://open.spotify.com/'),

                    'actionIntro' =>
                        null,

                    'actionNote' =>
                        null,
                ],
                function ($mail) use (
                    $renderedSubject
                ): void {
                    $mail
                        ->to(
                            $this->testEmail
                        )
                        ->subject(
                            $renderedSubject
                        );
                }
            );

            Notification::make()
                ->title('Test email sent')
                ->body(
                    "A branded eLive Events test email was sent to {$this->testEmail}."
                )
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report(
                $exception
            );

            Notification::make()
                ->title('Test email failed')
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
        if ($this->isTicketBuyerAudience()) {
            return $this->renderTicketBuyerTest($this->message, $event);
        }

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
                filled(
                    $this->testEmail
                )
                    ? $this->testEmail
                    : 'test@example.com',

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

    public function ticketBuyerTestPreview(): ?string
    {
        $event = $this->selectedEvent();

        return $event && $this->isTicketBuyerAudience() && filled($this->message)
            ? $this->renderTicketBuyerTest($this->message, $event)
            : null;
    }

    private function renderTicketBuyerTest(string $message, Event $event): string
    {
        $order = new TicketOrder(['buyer_name' => 'Test Buyer', 'order_number' => 'TEST-ORDER', 'quantity' => 2]);
        $order->setRelation('event', $event);
        $order->setRelation('items', collect());

        return app(TicketAccessMessageFactory::class)->renderForOrder(
            $message,
            $order,
            route('public.ticket-recovery.show'),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Queue Communication Campaign
    |--------------------------------------------------------------------------
    */

    public function queueCampaign(): void
    {
        $this->validate([
            'emailPoster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'emailActionUrl' => ['nullable', 'url', 'starts_with:https://', 'max:2048'],
            'emailActionLabel' => ['nullable', 'string', 'max:80'],
            'eventId' => [
                'required',
                'integer',
            ],

            'channel' => [
                'required',
                'in:sms,email',
            ],

            'campaignName' => [
                'required',
                'string',
                'max:255',
            ],

            'audience' => [
                'required',
                Rule::in(array_keys($this->audienceOptions)),
            ],

            'categoryId' => [
                'nullable',
                'integer',
            ],

            'templateId' => [
                'nullable',
                'integer',
            ],

            'subject' => [
                $this->channel
                === CommunicationTemplate::CHANNEL_EMAIL
                    ? 'required'
                    : 'nullable',
                'string',
                'max:255',
            ],

            'message' => [
                'required',
                'string',
                'max:10000',
            ],
        ]);

        if ($this->isTicketBuyerAudience()) {
            foreach (['subject', 'message'] as $field) {
                preg_match_all('/#[A-Z_]+#/', (string) $this->{$field}, $placeholders);
                $unsupported = array_diff($placeholders[0], $this->ticketBuyerPlaceholders());
                if ($unsupported !== []) {
                    $this->addError($field, 'Unsupported ticket buyer placeholders: '.implode(', ', array_unique($unsupported)).'.');

                    return;
                }
            }
        }

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
                ->title(
                    'No valid recipients'
                )
                ->body(
                    'The selected audience does not contain any valid '
                    . $this->recipientLabel()
                    . ' for '
                    . $this->channelLabel()
                    . '.'
                )
                ->warning()
                ->send();

            return;
        }

        if (
            ! $this->isTicketBuyerAudience()
            && $this->categoryId
            && ! $this->categories()->contains(
                fn ($category): bool =>
                    (int) $category->getKey()
                    === (int) $this->categoryId
            )
        ) {
            Notification::make()
                ->title('Invalid attendee category')
                ->body(
                    'The selected category does not belong to this event.'
                )
                ->danger()
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
            if ($this->channel === CommunicationTemplate::CHANNEL_EMAIL) {
                $this->saveEmailPoster();
            }

            $service =
                app(
                    CommunicationCampaignService::class
                );

            if (
                $this->channel
                === CommunicationTemplate::CHANNEL_SMS
            ) {
                $campaign =
                    $service->queueSmsCampaign(
                        event: $event,
                        name: $this->campaignName,
                        message: $this->message,
                        audience: $this->audience,
                        categoryId: $this->isTicketBuyerAudience() ? null : $this->categoryId,
                        template: $template,
                        createdBy: auth()->id()
                    );
            } else {
                $campaign =
                    $service->queueEmailCampaign(
                        event: $event,
                        name: $this->campaignName,
                        subject: (string) $this->subject,
                        message: $this->message,
                        audience: $this->audience,
                        categoryId: $this->isTicketBuyerAudience() ? null : $this->categoryId,
                        template: $template,
                        createdBy: auth()->id(),
                        emailPosterPath: $this->emailPosterPath,
                        emailActionUrl: $this->emailActionUrl ?: null,
                        emailActionLabel: $this->emailActionLabel ?: null
                    );
            }

            if ($this->isTicketBuyerAudience() && ! $campaign->wasRecentlyCreated) {
                Notification::make()->title('This campaign was already queued')->warning()->send();

                return;
            }

            Notification::make()
                ->title(
                    $this->channelLabel()
                    . ' campaign queued'
                )
                ->body(
                    "{$campaign->queued_count} messages queued. "
                    . "{$campaign->failed_count} recipients skipped."
                )
                ->success()
                ->send();

            /*
             * Reset only message-related fields.
             * Keep the event and audience selected.
             */
            $this->subject = null;
            $this->message = '';
            $this->templateId = null;
            $this->resetEmailArtwork();

            $this->campaignName =
                $this->defaultCampaignName(
                    $event
                );

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

        if (! $user instanceof User) {
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
                $this->channel
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
            ->with([
                'event:id,name',
                'template:id,name,key',
            ])
            ->latest('id')
            ->limit(10)
            ->get();
    }

    public function getCampaignStatsProperty(): array
    {
        $campaigns = $this->recentCampaigns();

        return [
            'campaigns' => $campaigns->count(),
            'queued' => (int) $campaigns->sum(
                fn (CommunicationCampaign $campaign): int =>
                    (int) ($campaign->queued_count ?? 0)
            ),
            'sent' => (int) $campaigns->sum(
                fn (CommunicationCampaign $campaign): int =>
                    (int) ($campaign->sent_count ?? 0)
            ),
            'failed' => (int) $campaigns->sum(
                fn (CommunicationCampaign $campaign): int =>
                    (int) ($campaign->failed_count ?? 0)
            ),
        ];
    }

    public function campaignStatusLabel(
        ?string $status
    ): string {
        return match ($status) {
            'draft' => 'Draft',
            'queued' => 'Queued',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            default => ucfirst(
                str_replace(
                    '_',
                    ' ',
                    (string) ($status ?: 'unknown')
                )
            ),
        };
    }

    public function campaignStatusTone(
        ?string $status
    ): string {
        return match ($status) {
            'completed' => 'success',
            'queued', 'processing' => 'warning',
            'failed' => 'danger',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Channel Helpers
    |--------------------------------------------------------------------------
    */

    public function isSmsChannel(): bool
    {
        return $this->channel
            === CommunicationTemplate::CHANNEL_SMS;
    }

    public function isEmailChannel(): bool
    {
        return $this->channel
            === CommunicationTemplate::CHANNEL_EMAIL;
    }

    public function recipientLabel(): string
    {
        return match ($this->channel) {
            CommunicationTemplate::CHANNEL_EMAIL =>
                'email addresses',

            CommunicationTemplate::CHANNEL_SMS =>
                'mobile numbers',

            CommunicationTemplate::CHANNEL_WHATSAPP =>
                'WhatsApp numbers',

            default =>
                'recipients',
        };
    }

    public function channelOptions(): array
    {
        return array_intersect_key(CommunicationTemplate::channelOptions(), array_flip(['sms', 'email']));
    }

    public function channelLabel(): string
    {
        return CommunicationTemplate::channelOptions()[
            $this->channel
        ]
            ?? str(
                $this->channel
            )
                ->headline()
                ->toString();
    }

    private function defaultCampaignName(
        Event $event
    ): string {
        return $event->name
            . ' '
            . $this->channelLabel()
            . ' Campaign';
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
            $user instanceof User
            && $user->canManageEventCommunication(
                $event
            ),
            403
        );
    }
}