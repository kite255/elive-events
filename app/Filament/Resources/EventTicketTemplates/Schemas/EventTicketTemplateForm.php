<?php

namespace App\Filament\Resources\EventTicketTemplates\Schemas;

use App\Models\Event;
use App\Models\OrganizationTicketTemplate;
use App\Models\TicketType;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EventTicketTemplateForm
{
    public static function configure(
        Schema $schema
    ): Schema {
        return $schema
            ->components([
                Section::make('Template Information')
                    ->schema([
                        Select::make('event_id')
                            ->label('Event')
                            ->options(
                                fn (): array =>
                                    static::eventOptions()
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('ticket_type_id')
                            ->label('Ticket Type')
                            ->options(
                                TicketType::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText(
                                'Leave blank to use this as the event default template.'
                            ),

                        Select::make(
                            'source_organization_template_id'
                        )
                            ->label(
                                'Source Organization Template'
                            )
                            ->options(
                                fn (): array =>
                                    static::organizationTemplateOptions()
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->visible(
                                fn (): bool =>
                                    auth()->user()?->isSuperAdmin()
                                        ?? false
                            ),

                        TextInput::make('name')
                            ->label('Template Name')
                            ->required()
                            ->maxLength(150),
                    ])
                    ->columns(2),

                Section::make('Canvas')
                    ->schema([
                        TextInput::make('width')
                            ->label('Width')
                            ->numeric()
                            ->minValue(300)
                            ->maxValue(4000)
                            ->default(1080)
                            ->required(),

                        TextInput::make('height')
                            ->label('Height')
                            ->numeric()
                            ->minValue(300)
                            ->maxValue(4000)
                            ->default(1350)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Availability')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Toggle::make('is_default')
                            ->label('Default Template')
                            ->default(false),
                    ])
                    ->columns(2),
            ]);
    }

    private static function eventOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            return Event::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        }

        return $user->eventManagerEvents()
            ->orderBy('events.name')
            ->pluck('events.name', 'events.id')
            ->all();
    }

    private static function organizationTemplateOptions(): array
    {
        $user = auth()->user();

        if (! $user?->isSuperAdmin()) {
            return [];
        }

        return OrganizationTicketTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
