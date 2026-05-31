<?php

namespace App\Filament\Resources\CommunicationTemplates\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CommunicationTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Details')
                    ->description('Create reusable SMS, email, and WhatsApp message templates.')
                    ->schema([
                        Select::make('organization_id')
                            ->label('Organization')
                            ->relationship('organization', 'name')
                            ->searchable(),

                        TextInput::make('name')
                            ->label('Template Name')
                            ->placeholder('Invitation SMS, RSVP Reminder, Badge Ready Message')
                            ->required()
                            ->maxLength(255),

                        Select::make('channel')
                            ->label('Channel')
                            ->options([
                                'sms' => 'SMS',
                                'email' => 'Email',
                                'whatsapp' => 'WhatsApp',
                            ])
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Message Content')
                    ->description('Use placeholders like {{ attendee_name }}, {{ event_name }}, {{ rsvp_link }}, {{ badge_link }}, {{ venue }}, {{ event_date }}.')
                    ->schema([
                        TextInput::make('subject')
                            ->label('Email Subject')
                            ->placeholder('You are invited to {{ event_name }}')
                            ->maxLength(255),

                        Textarea::make('body')
                            ->label('Message Body')
                            ->placeholder('Hello {{ attendee_name }}, you are invited to {{ event_name }} at {{ venue }}. RSVP here: {{ rsvp_link }}')
                            ->required()
                            ->rows(8)
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }
}