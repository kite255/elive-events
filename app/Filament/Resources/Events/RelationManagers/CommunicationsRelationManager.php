<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Filament\Resources\Events\EventResource;
use App\Models\AttendeeCategory;
use App\Models\CommunicationTemplate;
use App\Models\EventCommunication;
use App\Services\EventCommunicationCampaignService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Throwable;

class CommunicationsRelationManager extends RelationManager
{
    protected static string $relationship =
        'communications';

    protected static ?string $title =
        'Event Communications';

    protected static ?string $modelLabel =
        'Communication';

    protected static ?string $pluralModelLabel =
        'Communications';

    /*
    |--------------------------------------------------------------------------
    | Form
    |--------------------------------------------------------------------------
    |
    | Create and Edit now use dedicated full-page Filament pages.
    | The Relation Manager remains a clean communication list only.
    |
    */

    public function form(
        Schema $schema
    ): Schema {
        return $schema
            ->components([]);
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    public function table(
        Table $table
    ): Table {
        return $table
            ->recordTitleAttribute(
                'title'
            )
            ->defaultSort(
                'id',
                'desc'
            )
            ->columns([
                TextColumn::make('title')
                    ->label('Communication')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(
                        fn (
                            EventCommunication $record
                        ): string =>
                            $this->typeLabel(
                                $record->type
                            )
                    ),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(
                        fn (
                            ?string $state
                        ): string =>
                            $this->typeLabel(
                                $state
                            )
                    )
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(
                        fn (
                            ?string $state
                        ): string =>
                            match ($state) {
                                EventCommunication::STATUS_PUBLISHED =>
                                    'success',

                                EventCommunication::STATUS_SCHEDULED =>
                                    'info',

                                EventCommunication::STATUS_ARCHIVED =>
                                    'gray',

                                default =>
                                    'warning',
                            }
                    )
                    ->formatStateUsing(
                        fn (
                            ?string $state
                        ): string =>
                            ucfirst(
                                $state
                                ?: EventCommunication::STATUS_DRAFT
                            )
                    )
                    ->sortable(),

                IconColumn::make(
                    'is_public'
                )
                    ->label('Public')
                    ->boolean(),

                IconColumn::make(
                    'hero_enabled'
                )
                    ->label('Hero')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make(
                    'published_at'
                )
                    ->label('Published')
                    ->dateTime(
                        'd M Y, H:i'
                    )
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make(
                    'scheduled_at'
                )
                    ->label('Scheduled')
                    ->dateTime(
                        'd M Y, H:i'
                    )
                    ->placeholder('—')
                    ->toggleable(
                        isToggledHiddenByDefault:
                            true
                    ),

                TextColumn::make(
                    'created_at'
                )
                    ->label('Created')
                    ->dateTime(
                        'd M Y, H:i'
                    )
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault:
                            true
                    ),
            ])
            ->filters([
                SelectFilter::make(
                    'type'
                )
                    ->options(
                        $this->typeOptions()
                    ),

                SelectFilter::make(
                    'status'
                )
                    ->options([
                        EventCommunication::STATUS_DRAFT =>
                            'Draft',

                        EventCommunication::STATUS_PUBLISHED =>
                            'Published',

                        EventCommunication::STATUS_SCHEDULED =>
                            'Scheduled',

                        EventCommunication::STATUS_ARCHIVED =>
                            'Archived',
                    ]),

                TernaryFilter::make(
                    'is_public'
                )
                    ->label(
                        'Public Page'
                    )
                    ->trueLabel(
                        'Public only'
                    )
                    ->falseLabel(
                        'Private only'
                    )
                    ->native(false),
            ])
            ->headerActions([
                Action::make(
                    'createCommunication'
                )
                    ->label(
                        'Create Communication'
                    )
                    ->icon(
                        'heroicon-o-plus'
                    )
                    ->color('primary')
                    ->url(
                        fn (): string =>
                            EventResource::getUrl(
                                'communication-create',
                                [
                                    'event' =>
                                        $this
                                            ->getOwnerRecord(),
                                ]
                            )
                    ),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon(
                        'heroicon-o-pencil-square'
                    )
                    ->color('primary')
                    ->url(
                        fn (
                            EventCommunication $record
                        ): string =>
                            EventResource::getUrl(
                                'communication-edit',
                                [
                                    'event' =>
                                        $this
                                            ->getOwnerRecord(),

                                    'communication' =>
                                        $record,
                                ]
                            )
                    ),

                Action::make('sendTestEmail')
                    ->label('Send Test Email')
                    ->icon(
                        'heroicon-o-envelope'
                    )
                    ->color('info')
                    ->modalHeading(
                        fn (
                            EventCommunication $record
                        ): string =>
                            'Send Test Email: '
                            . $record->title
                    )
                    ->modalDescription(
                        'Send the exact Event Communication email to one address before broadcasting it to attendees.'
                    )
                    ->modalSubmitActionLabel(
                        'Send Test Email'
                    )
                    ->form([
                        TextInput::make('email')
                            ->label('Test Email Address')
                            ->email()
                            ->required()
                            ->default(
                                fn (): ?string =>
                                    auth()->user()?->email
                            )
                            ->placeholder(
                                'name@example.com'
                            ),
                    ])
                    ->action(
                        function (
                            EventCommunication $record,
                            array $data
                        ): void {
                            try {
                                $record->loadMissing([
                                    'event.organization',
                                    'sections',
                                    'links',
                                    'images',
                                    'attachments',
                                ]);

                                $event =
                                    $record->event
                                    ?? $this->getOwnerRecord();

                                $recipient =
                                    (string) $data['email'];

                                $subject =
                                    '[TEST] '
                                    . $record->title;

                                Mail::send(
                                    'emails.event-communication',
                                    [
                                        'subject' =>
                                            $subject,

                                        'communication' =>
                                            $record,

                                        'event' =>
                                            $event,
                                    ],
                                    function ($mail) use (
                                        $recipient,
                                        $subject
                                    ): void {
                                        $mail
                                            ->to($recipient)
                                            ->subject($subject);
                                    }
                                );

                                Notification::make()
                                    ->title(
                                        'Test email sent'
                                    )
                                    ->body(
                                        'The Event Communication test email was sent to '
                                        . $recipient
                                        . '. Review it before broadcasting.'
                                    )
                                    ->success()
                                    ->send();
                            } catch (
                                Throwable $exception
                            ) {
                                report($exception);

                                Notification::make()
                                    ->title(
                                        'Test email could not be sent'
                                    )
                                    ->body(
                                        $exception->getMessage()
                                    )
                                    ->danger()
                                    ->send();
                            }
                        }
                    ),

                Action::make('sendCommunication')
                    ->label('Broadcast')
                    ->icon(
                        'heroicon-o-paper-airplane'
                    )
                    ->color('success')
                    ->visible(
                        fn (
                            EventCommunication $record
                        ): bool =>
                            $record->is_public
                            && $record
                                ->isPublished()
                    )
                    ->modalHeading(
                        fn (
                            EventCommunication $record
                        ): string =>
                            'Send: '
                            . $record->title
                    )
                    ->modalDescription(
                        'Broadcast this communication using the existing campaign engine. For email, send and review a test email first.'
                    )
                    ->modalWidth('2xl')
                    ->modalSubmitActionLabel(
                        'Continue'
                    )
                    ->form([
                        Select::make('channel')
                            ->label('Channel')
                            ->options([
                                CommunicationTemplate::CHANNEL_EMAIL =>
                                    'Email',

                                CommunicationTemplate::CHANNEL_SMS =>
                                    'SMS',

                                CommunicationTemplate::CHANNEL_WHATSAPP =>
                                    'WhatsApp (requires dedicated Meta template)',
                            ])
                            ->default(
                                CommunicationTemplate::CHANNEL_EMAIL
                            )
                            ->required()
                            ->native(false)
                            ->live(),

                        Checkbox::make('test_reviewed')
                            ->label(
                                'I have sent and reviewed a test email'
                            )
                            ->helperText(
                                'Required before broadcasting an email campaign.'
                            )
                            ->visible(
                                fn (
                                    Get $get
                                ): bool =>
                                    ($get('channel')
                                        ?: CommunicationTemplate::CHANNEL_EMAIL)
                                    === CommunicationTemplate::CHANNEL_EMAIL
                            )
                            ->required(
                                fn (
                                    Get $get
                                ): bool =>
                                    ($get('channel')
                                        ?: CommunicationTemplate::CHANNEL_EMAIL)
                                    === CommunicationTemplate::CHANNEL_EMAIL
                            )
                            ->rules([
                                'accepted',
                            ]),

                        Select::make('audience')
                            ->label('Recipients')
                            ->options([
                                'all' =>
                                    'All Attendees',

                                'approved' =>
                                    'Approved / Confirmed',

                                'registered' =>
                                    'Registered',

                                'confirmed' =>
                                    'Confirmed',

                                'checked_in' =>
                                    'Checked-in Attendees',

                                'not_checked_in' =>
                                    'Not Checked-in',

                                'pending_approval' =>
                                    'Pending Approval',

                                'waitlisted' =>
                                    'Waitlisted',
                            ])
                            ->default('all')
                            ->required()
                            ->native(false)
                            ->live(),

                        Select::make('category_id')
                            ->label(
                                'Participant Category'
                            )
                            ->placeholder(
                                'All categories'
                            )
                            ->options(
                                fn (): array =>
                                    AttendeeCategory::query()
                                        ->where(
                                            'event_id',
                                            $this
                                                ->getOwnerRecord()
                                                ->getKey()
                                        )
                                        ->orderBy('name')
                                        ->pluck(
                                            'name',
                                            'id'
                                        )
                                        ->all()
                            )
                            ->searchable()
                            ->native(false)
                            ->live(),

                        Placeholder::make(
                            'recipient_preview'
                        )
                            ->label(
                                'Recipient Preview'
                            )
                            ->content(
                                function (
                                    EventCommunication $record,
                                    Get $get
                                ): string {
                                    $channel =
                                        $get('channel')
                                        ?: CommunicationTemplate::CHANNEL_EMAIL;

                                    $audience =
                                        $get('audience')
                                        ?: 'all';

                                    $categoryId =
                                        filled(
                                            $get(
                                                'category_id'
                                            )
                                        )
                                            ? (int)
                                                $get(
                                                    'category_id'
                                                )
                                            : null;

                                    try {
                                        $preview =
                                            app(
                                                EventCommunicationCampaignService::class
                                            )->preview(
                                                $record,
                                                $channel,
                                                $audience,
                                                $categoryId
                                            );

                                        return
                                            'Total: '
                                            . number_format(
                                                (int) (
                                                    $preview[
                                                        'total'
                                                    ]
                                                    ?? 0
                                                )
                                            )
                                            . ' · Valid: '
                                            . number_format(
                                                (int) (
                                                    $preview[
                                                        'valid'
                                                    ]
                                                    ?? 0
                                                )
                                            )
                                            . ' · Invalid: '
                                            . number_format(
                                                (int) (
                                                    $preview[
                                                        'invalid'
                                                    ]
                                                    ?? 0
                                                )
                                            );
                                    } catch (
                                        Throwable
                                    ) {
                                        return
                                            'Recipient preview is unavailable.';
                                    }
                                }
                            ),

                        Select::make('delivery')
                            ->label('Delivery')
                            ->options([
                                'now' =>
                                    'Send Now',

                                'schedule' =>
                                    'Schedule',
                            ])
                            ->default('now')
                            ->required()
                            ->native(false)
                            ->live(),

                        DateTimePicker::make(
                            'scheduled_at'
                        )
                            ->label('Send At')
                            ->seconds(false)
                            ->native(false)
                            ->minDate(now())
                            ->visible(
                                fn (
                                    Get $get
                                ): bool =>
                                    $get('delivery')
                                    === 'schedule'
                            )
                            ->required(
                                fn (
                                    Get $get
                                ): bool =>
                                    $get('delivery')
                                    === 'schedule'
                            ),
                    ])
                    ->action(
                        function (
                            EventCommunication $record,
                            array $data
                        ): void {
                            try {
                                $service =
                                    app(
                                        EventCommunicationCampaignService::class
                                    );

                                $channel =
                                    (string)
                                    $data['channel'];

                                $audience =
                                    (string)
                                    (
                                        $data[
                                            'audience'
                                        ]
                                        ?? 'all'
                                    );

                                $categoryId =
                                    filled(
                                        $data[
                                            'category_id'
                                        ]
                                        ?? null
                                    )
                                        ? (int)
                                            $data[
                                                'category_id'
                                            ]
                                        : null;

                                /*
                                 * WhatsApp remains protected until a dedicated
                                 * Event Communication Meta template is added.
                                 */
                                if (
                                    $channel
                                    === CommunicationTemplate::CHANNEL_WHATSAPP
                                ) {
                                    Notification::make()
                                        ->title(
                                            'WhatsApp not enabled for Event Communications yet'
                                        )
                                        ->body(
                                            'Your current WhatsApp job sends the registration-confirmation template. Add an approved event-update template before enabling this channel.'
                                        )
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                if (
                                    (
                                        $data[
                                            'delivery'
                                        ]
                                        ?? 'now'
                                    )
                                    === 'schedule'
                                ) {
                                    $scheduledAt =
                                        \Illuminate\Support\Carbon::parse(
                                            $data[
                                                'scheduled_at'
                                            ]
                                        );

                                    $service->schedule(
                                        communication:
                                            $record,
                                        channel:
                                            $channel,
                                        audience:
                                            $audience,
                                        categoryId:
                                            $categoryId,
                                        scheduledAt:
                                            $scheduledAt,
                                        createdBy:
                                            auth()->id()
                                    );

                                    Notification::make()
                                        ->title(
                                            'Communication scheduled'
                                        )
                                        ->body(
                                            'The campaign will be created and queued at '
                                            . $scheduledAt
                                                ->format(
                                                    'd M Y, H:i'
                                                )
                                            . '.'
                                        )
                                        ->success()
                                        ->send();

                                    return;
                                }

                                $preview =
                                    $service->preview(
                                        $record,
                                        $channel,
                                        $audience,
                                        $categoryId
                                    );

                                if (
                                    (int) (
                                        $preview[
                                            'valid'
                                        ]
                                        ?? 0
                                    )
                                    <= 0
                                ) {
                                    Notification::make()
                                        ->title(
                                            'No valid recipients'
                                        )
                                        ->body(
                                            'The selected audience has no valid recipients for this channel.'
                                        )
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                $campaign =
                                    $service->sendNow(
                                        communication:
                                            $record,
                                        channel:
                                            $channel,
                                        audience:
                                            $audience,
                                        categoryId:
                                            $categoryId,
                                        createdBy:
                                            auth()->id()
                                    );

                                Notification::make()
                                    ->title(
                                        'Communication campaign queued'
                                    )
                                    ->body(
                                        number_format(
                                            (int)
                                            $campaign
                                                ->queued_count
                                        )
                                        . ' message(s) queued. '
                                        . number_format(
                                            (int)
                                            $campaign
                                                ->failed_count
                                        )
                                        . ' recipient(s) skipped.'
                                    )
                                    ->success()
                                    ->send();
                            } catch (
                                Throwable $exception
                            ) {
                                report(
                                    $exception
                                );

                                Notification::make()
                                    ->title(
                                        'Communication could not be sent'
                                    )
                                    ->body(
                                        $exception
                                            ->getMessage()
                                    )
                                    ->danger()
                                    ->send();
                            }
                        }
                    ),

                Action::make('preview')
                    ->label('Preview')
                    ->icon(
                        'heroicon-o-eye'
                    )
                    ->color('info')
                    ->url(
                        fn (
                            EventCommunication $record
                        ): string =>
                            route(
                                'admin.event-communications.preview',
                                $record
                            )
                    )
                    ->openUrlInNewTab(),

                Action::make(
                    'public_page'
                )
                    ->label(
                        'Open Public Page'
                    )
                    ->icon(
                        'heroicon-o-arrow-top-right-on-square'
                    )
                    ->color('gray')
                    ->visible(
                        fn (
                            EventCommunication $record
                        ): bool =>
                            $record
                                ->is_public
                            && $record
                                ->isPublished()
                    )
                    ->url(
                        fn (
                            EventCommunication $record
                        ): string =>
                            $record
                                ->publicUrl()
                    )
                    ->openUrlInNewTab(),

                Action::make('publish')
                    ->label('Publish')
                    ->icon(
                        'heroicon-o-paper-airplane'
                    )
                    ->color('success')
                    ->visible(
                        fn (
                            EventCommunication $record
                        ): bool =>
                            ! $record
                                ->isPublished()
                    )
                    ->requiresConfirmation()
                    ->modalHeading(
                        'Publish this communication?'
                    )
                    ->modalDescription(
                        'The communication will become available immediately when its public page is enabled.'
                    )
                    ->action(
                        function (
                            EventCommunication $record
                        ): void {
                            $record->update([
                                'status' =>
                                    EventCommunication::STATUS_PUBLISHED,

                                'is_public' =>
                                    true,

                                'published_at' =>
                                    now(),

                                'scheduled_at' =>
                                    null,
                            ]);

                            Notification::make()
                                ->title(
                                    'Communication published'
                                )
                                ->body(
                                    $record->title
                                )
                                ->success()
                                ->send();
                        }
                    ),

                Action::make('archive')
                    ->label('Archive')
                    ->icon(
                        'heroicon-o-archive-box'
                    )
                    ->color('gray')
                    ->visible(
                        fn (
                            EventCommunication $record
                        ): bool =>
                            $record->status
                            !== EventCommunication::STATUS_ARCHIVED
                    )
                    ->requiresConfirmation()
                    ->modalHeading(
                        'Archive this communication?'
                    )
                    ->action(
                        function (
                            EventCommunication $record
                        ): void {
                            $record->update([
                                'status' =>
                                    EventCommunication::STATUS_ARCHIVED,

                                'is_public' =>
                                    false,
                            ]);

                            Notification::make()
                                ->title(
                                    'Communication archived'
                                )
                                ->success()
                                ->send();
                        }
                    ),

                DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->modifyQueryUsing(
                fn (
                    Builder $query
                ): Builder =>
                    $query
                        ->latest('id')
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function typeOptions(): array
    {
        return [
            'announcement' =>
                'Announcement',

            'highlight' =>
                "Today's Highlights",

            'update' =>
                'Event Update',

            'schedule' =>
                'Schedule / Program',

            'reminder' =>
                'Reminder',

            'notice' =>
                'Important Notice',

            'emergency' =>
                'Emergency Alert',

            'summary' =>
                'Event Summary',

            'custom' =>
                'Custom',
        ];
    }

    protected function typeLabel(
        ?string $type
    ): string {
        return $this
            ->typeOptions()[
                $type
                ?? ''
            ]
            ?? ucfirst(
                str_replace(
                    '_',
                    ' ',
                    $type
                    ?: 'Unknown'
                )
            );
    }
}
