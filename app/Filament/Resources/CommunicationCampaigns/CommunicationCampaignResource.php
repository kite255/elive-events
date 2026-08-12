<?php

namespace App\Filament\Resources\CommunicationCampaigns;

use App\Filament\Resources\CommunicationCampaigns\Pages\ListCommunicationCampaigns;
use App\Filament\Resources\CommunicationCampaigns\Pages\ViewCommunicationCampaign;
use App\Filament\Resources\CommunicationCampaigns\RelationManagers\LogsRelationManager;
use App\Models\CommunicationCampaign;
use App\Models\Event;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CommunicationCampaignResource extends Resource
{
    protected static ?string $model =
        CommunicationCampaign::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel =
        'Campaigns';

    protected static ?string $modelLabel =
        'Communication Campaign';

    protected static ?string $pluralModelLabel =
        'Communication Campaigns';

    protected static string|UnitEnum|null $navigationGroup =
        'Communication';

    protected static ?int $navigationSort = 21;

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    public static function canCreate(): bool
    {
        /*
         * Campaign creation is handled from
         * Communication Center.
         */
        return false;
    }

    public static function canEdit(
        $record
    ): bool {
        return false;
    }

    public static function canDelete(
        $record
    ): bool {
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Query
    |--------------------------------------------------------------------------
    */

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'event.organization',
                'creator',
                'template',
            ]);

        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw(
                '1 = 0'
            );
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        $eventIds = Event::query()
            ->accessibleBy($user)
            ->get()
            ->filter(
                fn (Event $event): bool =>
                    $user->canManageEventCommunication(
                        $event
                    )
            )
            ->pluck('id');

        return $query->whereIn(
            'event_id',
            $eventIds
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Infolist
    |--------------------------------------------------------------------------
    */

    public static function infolist(
        Schema $schema
    ): Schema {
        return $schema
            ->components([

                Section::make(
                    'Campaign Information'
                )
                    ->schema([

                        Grid::make(3)
                            ->schema([

                                \Filament\Infolists\Components\TextEntry::make(
                                    'name'
                                )
                                    ->label(
                                        'Campaign Name'
                                    ),

                                \Filament\Infolists\Components\TextEntry::make(
                                    'event.name'
                                )
                                    ->label(
                                        'Event'
                                    ),

                                \Filament\Infolists\Components\TextEntry::make(
                                    'event.organization.name'
                                )
                                    ->label(
                                        'Organization'
                                    )
                                    ->placeholder(
                                        '—'
                                    ),

                                \Filament\Infolists\Components\TextEntry::make(
                                    'channel'
                                )
                                    ->label(
                                        'Channel'
                                    )
                                    ->formatStateUsing(
                                        fn (
                                            CommunicationCampaign $record
                                        ): string =>
                                            $record->channelLabel()
                                    )
                                    ->badge(),

                                \Filament\Infolists\Components\TextEntry::make(
                                    'status'
                                )
                                    ->label(
                                        'Status'
                                    )
                                    ->formatStateUsing(
                                        fn (
                                            CommunicationCampaign $record
                                        ): string =>
                                            $record->statusLabel()
                                    )
                                    ->badge()
                                    ->color(
                                        fn (
                                            string $state
                                        ): string =>
                                            self::statusColor(
                                                $state
                                            )
                                    ),

                                \Filament\Infolists\Components\TextEntry::make(
                                    'audience'
                                )
                                    ->label(
                                        'Audience'
                                    )
                                    ->state(
                                        fn (
                                            CommunicationCampaign $record
                                        ): string =>
                                            $record->audienceLabel()
                                    ),

                                \Filament\Infolists\Components\TextEntry::make(
                                    'creator.name'
                                )
                                    ->label(
                                        'Created By'
                                    )
                                    ->placeholder(
                                        '—'
                                    ),

                                \Filament\Infolists\Components\TextEntry::make(
                                    'template.name'
                                )
                                    ->label(
                                        'Template'
                                    )
                                    ->placeholder(
                                        'Manual message'
                                    ),
                            ]),
                    ]),

                Section::make(
                    'Campaign Progress'
                )
                    ->schema([

                        Grid::make(4)
                            ->schema([

                                \Filament\Infolists\Components\TextEntry::make(
                                    'total_recipients'
                                )
                                    ->label(
                                        'Recipients'
                                    )
                                    ->numeric(),

                                \Filament\Infolists\Components\TextEntry::make(
                                    'queued_count'
                                )
                                    ->label(
                                        'Queued'
                                    )
                                    ->numeric(),

                                \Filament\Infolists\Components\TextEntry::make(
                                    'sent_count'
                                )
                                    ->label(
                                        'Sent'
                                    )
                                    ->numeric(),

                                \Filament\Infolists\Components\TextEntry::make(
                                    'delivered_count'
                                )
                                    ->label(
                                        'Delivered'
                                    )
                                    ->numeric(),

                                \Filament\Infolists\Components\TextEntry::make(
                                    'failed_count'
                                )
                                    ->label(
                                        'Failed'
                                    )
                                    ->numeric(),

                                \Filament\Infolists\Components\TextEntry::make(
                                    'processed_count'
                                )
                                    ->label(
                                        'Processed'
                                    )
                                    ->state(
                                        fn (
                                            CommunicationCampaign $record
                                        ): string =>
                                            number_format(
                                                $record->processedCount()
                                            )
                                    ),

                                \Filament\Infolists\Components\TextEntry::make(
                                    'remaining_count'
                                )
                                    ->label(
                                        'Remaining'
                                    )
                                    ->state(
                                        fn (
                                            CommunicationCampaign $record
                                        ): string =>
                                            number_format(
                                                $record->remainingCount()
                                            )
                                    ),

                                \Filament\Infolists\Components\TextEntry::make(
                                    'completion_percentage'
                                )
                                    ->label(
                                        'Progress'
                                    )
                                    ->state(
                                        fn (
                                            CommunicationCampaign $record
                                        ): string =>
                                            number_format(
                                                $record->completionPercentage(),
                                                1
                                            )
                                            . '%'
                                    ),
                            ]),
                    ]),

                Section::make(
                    'Message'
                )
                    ->schema([

                        \Filament\Infolists\Components\TextEntry::make(
                            'subject'
                        )
                            ->label(
                                'Subject'
                            )
                            ->placeholder(
                                'Not applicable'
                            ),

                        \Filament\Infolists\Components\TextEntry::make(
                            'message'
                        )
                            ->label(
                                'Message'
                            )
                            ->columnSpanFull(),
                    ]),

                Section::make(
                    'Timing'
                )
                    ->schema([

                        Grid::make(4)
                            ->schema([

                                \Filament\Infolists\Components\TextEntry::make(
                                    'created_at'
                                )
                                    ->label(
                                        'Created'
                                    )
                                    ->dateTime(),

                                \Filament\Infolists\Components\TextEntry::make(
                                    'started_at'
                                )
                                    ->label(
                                        'Started'
                                    )
                                    ->dateTime()
                                    ->placeholder(
                                        '—'
                                    ),

                                \Filament\Infolists\Components\TextEntry::make(
                                    'completed_at'
                                )
                                    ->label(
                                        'Completed'
                                    )
                                    ->dateTime()
                                    ->placeholder(
                                        '—'
                                    ),

                                \Filament\Infolists\Components\TextEntry::make(
                                    'scheduled_at'
                                )
                                    ->label(
                                        'Scheduled'
                                    )
                                    ->dateTime()
                                    ->placeholder(
                                        '—'
                                    ),
                            ]),
                    ]),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    public static function table(
        Table $table
    ): Table {
        return $table
            ->defaultSort(
                'id',
                'desc'
            )
            ->paginated([
                25,
                50,
                100,
            ])
            ->columns([

                TextColumn::make(
                    'name'
                )
                    ->label(
                        'Campaign'
                    )
                    ->searchable()
                    ->sortable()
                    ->limit(32),

                TextColumn::make(
                    'event.name'
                )
                    ->label(
                        'Event'
                    )
                    ->searchable()
                    ->sortable()
                    ->limit(28),

                TextColumn::make(
                    'channel'
                )
                    ->label(
                        'Channel'
                    )
                    ->badge()
                    ->formatStateUsing(
                        fn (
                            string $state
                        ): string =>
                            match ($state) {
                                CommunicationCampaign::CHANNEL_SMS =>
                                    'SMS',

                                CommunicationCampaign::CHANNEL_WHATSAPP =>
                                    'WhatsApp',

                                CommunicationCampaign::CHANNEL_EMAIL =>
                                    'Email',

                                default =>
                                    str($state)
                                        ->headline()
                                        ->toString(),
                            }
                    )
                    ->color(
                        fn (
                            string $state
                        ): string =>
                            match ($state) {
                                CommunicationCampaign::CHANNEL_SMS =>
                                    'info',

                                CommunicationCampaign::CHANNEL_WHATSAPP =>
                                    'success',

                                CommunicationCampaign::CHANNEL_EMAIL =>
                                    'primary',

                                default =>
                                    'gray',
                            }
                    ),

                TextColumn::make(
                    'status'
                )
                    ->label(
                        'Status'
                    )
                    ->badge()
                    ->formatStateUsing(
                        fn (
                            string $state
                        ): string =>
                            str(
                                $state
                            )->headline()
                    )
                    ->color(
                        fn (
                            string $state
                        ): string =>
                            self::statusColor(
                                $state
                            )
                    ),

                TextColumn::make(
                    'total_recipients'
                )
                    ->label(
                        'Recipients'
                    )
                    ->numeric()
                    ->sortable(),

                TextColumn::make(
                    'queued_count'
                )
                    ->label(
                        'Queued'
                    )
                    ->numeric()
                    ->sortable(),

                TextColumn::make(
                    'sent_count'
                )
                    ->label(
                        'Sent'
                    )
                    ->numeric()
                    ->sortable(),

                TextColumn::make(
                    'delivered_count'
                )
                    ->label(
                        'Delivered'
                    )
                    ->numeric()
                    ->sortable(),

                TextColumn::make(
                    'failed_count'
                )
                    ->label(
                        'Failed'
                    )
                    ->numeric()
                    ->sortable()
                    ->color(
                        fn (
                            CommunicationCampaign $record
                        ): string =>
                            (int) $record->failed_count > 0
                                ? 'danger'
                                : 'gray'
                    ),

                TextColumn::make(
                    'remaining'
                )
                    ->label(
                        'Remaining'
                    )
                    ->state(
                        fn (
                            CommunicationCampaign $record
                        ): string =>
                            number_format(
                                $record->remainingCount()
                            )
                    )
                    ->color(
                        fn (
                            CommunicationCampaign $record
                        ): string =>
                            $record->remainingCount() > 0
                                ? 'warning'
                                : 'success'
                    ),

                TextColumn::make(
                    'progress'
                )
                    ->label(
                        'Progress'
                    )
                    ->state(
                        fn (
                            CommunicationCampaign $record
                        ): string =>
                            number_format(
                                $record->completionPercentage(),
                                1
                            )
                            . '%'
                    ),

                TextColumn::make(
                    'created_at'
                )
                    ->label(
                        'Created'
                    )
                    ->dateTime(
                        'd M Y, H:i'
                    )
                    ->sortable(),

                TextColumn::make(
                    'completed_at'
                )
                    ->label(
                        'Completed'
                    )
                    ->dateTime(
                        'd M Y, H:i'
                    )
                    ->placeholder(
                        '—'
                    )
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),
            ])
            ->filters([

                SelectFilter::make(
                    'event_id'
                )
                    ->label(
                        'Event'
                    )
                    ->relationship(
                        'event',
                        'name'
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make(
                    'channel'
                )
                    ->options([
                        CommunicationCampaign::CHANNEL_SMS =>
                            'SMS',

                        CommunicationCampaign::CHANNEL_WHATSAPP =>
                            'WhatsApp',

                        CommunicationCampaign::CHANNEL_EMAIL =>
                            'Email',
                    ]),

                SelectFilter::make(
                    'status'
                )
                    ->options([
                        CommunicationCampaign::STATUS_DRAFT =>
                            'Draft',

                        CommunicationCampaign::STATUS_SCHEDULED =>
                            'Scheduled',

                        CommunicationCampaign::STATUS_QUEUED =>
                            'Queued',

                        CommunicationCampaign::STATUS_PROCESSING =>
                            'Processing',

                        CommunicationCampaign::STATUS_COMPLETED =>
                            'Completed',

                        CommunicationCampaign::STATUS_FAILED =>
                            'Failed',

                        CommunicationCampaign::STATUS_CANCELLED =>
                            'Cancelled',
                    ]),

                Filter::make(
                    'has_failures'
                )
                    ->label(
                        'Has failures'
                    )
                    ->query(
                        fn (
                            Builder $query
                        ): Builder =>
                            $query->where(
                                'failed_count',
                                '>',
                                0
                            )
                    ),

                Filter::make(
                    'incomplete'
                )
                    ->label(
                        'Incomplete campaigns'
                    )
                    ->query(
                        fn (
                            Builder $query
                        ): Builder =>
                            $query->whereIn(
                                'status',
                                [
                                    CommunicationCampaign::STATUS_QUEUED,
                                    CommunicationCampaign::STATUS_PROCESSING,
                                ]
                            )
                    ),

                Filter::make(
                    'stuck'
                )
                    ->label(
                        'Possibly stuck'
                    )
                    ->query(
                        fn (
                            Builder $query
                        ): Builder =>
                            $query
                                ->whereIn(
                                    'status',
                                    [
                                        CommunicationCampaign::STATUS_QUEUED,
                                        CommunicationCampaign::STATUS_PROCESSING,
                                    ]
                                )
                                ->where(
                                    'updated_at',
                                    '<=',
                                    now()->subMinutes(10)
                                )
                    ),

                Filter::make(
                    'today'
                )
                    ->label(
                        'Created today'
                    )
                    ->query(
                        fn (
                            Builder $query
                        ): Builder =>
                            $query->whereDate(
                                'created_at',
                                today()
                            )
                    ),
            ])
            ->recordActions([

                ViewAction::make()
                    ->label(
                        'Open Campaign'
                    ),

                Action::make(
                    'refresh_counters'
                )
                    ->label(
                        'Refresh Counters'
                    )
                    ->icon(
                        'heroicon-o-arrow-path'
                    )
                    ->color(
                        'gray'
                    )
                    ->action(
                        function (
                            CommunicationCampaign $record
                        ): void {
                            $record
                                ->refreshCounters();

                            Notification::make()
                                ->title(
                                    'Campaign counters refreshed'
                                )
                                ->body(
                                    'Campaign totals have been recalculated.'
                                )
                                ->success()
                                ->send();
                        }
                    ),
            ])
            ->recordUrl(
                fn (
                    CommunicationCampaign $record
                ): string =>
                    static::getUrl(
                        'view',
                        [
                            'record' =>
                                $record,
                        ]
                    )
            )
            ->emptyStateHeading(
                'No communication campaigns'
            )
            ->emptyStateDescription(
                'Campaigns created from Communication Center will appear here.'
            )
            ->emptyStateIcon(
                'heroicon-o-megaphone'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public static function getRelations(): array
    {
        return [
            LogsRelationManager::class,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    public static function getPages(): array
    {
        return [
            'index' =>
                ListCommunicationCampaigns::route(
                    '/'
                ),

            'view' =>
                ViewCommunicationCampaign::route(
                    '/{record}'
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Colors
    |--------------------------------------------------------------------------
    */

    private static function statusColor(
        string $status
    ): string {
        return match ($status) {
            CommunicationCampaign::STATUS_COMPLETED =>
                'success',

            CommunicationCampaign::STATUS_FAILED =>
                'danger',

            CommunicationCampaign::STATUS_PROCESSING =>
                'warning',

            CommunicationCampaign::STATUS_QUEUED =>
                'info',

            CommunicationCampaign::STATUS_SCHEDULED =>
                'info',

            CommunicationCampaign::STATUS_CANCELLED =>
                'gray',

            default =>
                'gray',
        };
    }
}