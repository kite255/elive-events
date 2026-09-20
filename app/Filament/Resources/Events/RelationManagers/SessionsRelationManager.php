<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Models\EventDay;
use App\Models\EventSession;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';

    protected static ?string $title = 'Sessions / Activities';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Session Details')
                    ->schema([
                        Select::make('event_day_id')
                            ->label('Event Day')
                            ->options(
                                fn (): array =>
                                    EventDay::query()
                                        ->where(
                                            'event_id',
                                            $this->getOwnerRecord()->getKey()
                                        )
                                        ->orderBy('display_order')
                                        ->orderBy('event_date')
                                        ->orderBy('id')
                                        ->get()
                                        ->mapWithKeys(
                                            fn (EventDay $day): array => [
                                                $day->getKey() =>
                                                    $day->name
                                                    . (
                                                        $day->event_date
                                                            ? ' — ' . $day->event_date->format('d M Y')
                                                            : ''
                                                    ),
                                            ]
                                        )
                                        ->all()
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required(),

                        TextInput::make('name')
                            ->label('Session / Activity Name')
                            ->required()
                            ->maxLength(255),

                        Select::make('session_type')
                            ->label('Type')
                            ->options([
                                'session' => 'Session',
                                'keynote' => 'Keynote',
                                'workshop' => 'Workshop',
                                'panel' => 'Panel',
                                'meeting' => 'Meeting',
                                'match' => 'Match',
                                'game' => 'Game',
                                'competition' => 'Competition',
                                'performance' => 'Performance',
                                'ceremony' => 'Ceremony',
                                'networking' => 'Networking',
                                'break' => 'Break',
                            ])
                            ->default('session')
                            ->native(false)
                            ->required(),

                        Select::make('status')
                            ->options([
                                EventSession::STATUS_DRAFT => 'Draft',
                                EventSession::STATUS_ACTIVE => 'Active',
                                EventSession::STATUS_COMPLETED => 'Completed',
                                EventSession::STATUS_CANCELLED => 'Cancelled',
                            ])
                            ->default(EventSession::STATUS_ACTIVE)
                            ->native(false)
                            ->required(),

                        DateTimePicker::make('starts_at')
                            ->label('Starts At')
                            ->seconds(false),

                        DateTimePicker::make('ends_at')
                            ->label('Ends At')
                            ->seconds(false)
                            ->afterOrEqual('starts_at'),

                        TextInput::make('venue_name')
                            ->label('Venue / Room / Field')
                            ->maxLength(255),

                        TextInput::make('capacity')
                            ->numeric()
                            ->minValue(1)
                            ->nullable(),

                        TextInput::make('display_order')
                            ->label('Display Order')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),

                        Toggle::make('requires_registration')
                            ->label('Requires Registration')
                            ->helperText(
                                'Attendees must explicitly select this session/activity.'
                            )
                            ->live(),

                        Toggle::make('registration_is_open')
                            ->label('Registration Open')
                            ->default(true),

                        Toggle::make('requires_check_in')
                            ->label('Requires Session Check-in')
                            ->helperText(
                                'Enable later when session-level QR attendance is used.'
                            ),

                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder =>
                    $query->with('eventDay')
            )
            ->columns([
                TextColumn::make('eventDay.name')
                    ->label('Event Day')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Session / Activity')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('session_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            ucwords(
                                str_replace('_', ' ', $state ?: 'session')
                            )
                    ),

                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('venue_name')
                    ->label('Venue')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('capacity')
                    ->label('Capacity')
                    ->placeholder('Unlimited')
                    ->numeric(),

                TextColumn::make('attendees_count')
                    ->label('Registered')
                    ->counts('attendees'),

                IconColumn::make('requires_registration')
                    ->label('Registration')
                    ->boolean(),

                IconColumn::make('requires_check_in')
                    ->label('Check-in')
                    ->boolean(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            ucfirst($state ?: 'draft')
                    ),
            ])
            ->defaultSort('display_order')
            ->headerActions([
                CreateAction::make()
                    ->label('Add Session / Activity')
                    ->mutateDataUsing(
                        function (array $data): array {
                            $data['event_id'] =
                                $this->getOwnerRecord()->getKey();

                            return $data;
                        }
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
