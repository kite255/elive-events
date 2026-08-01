<?php

namespace App\Filament\Resources\Events\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class RegistrationFieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrationFields';

    protected static ?string $title = 'Registration Fields';

    protected static ?string $modelLabel = 'Registration Field';

    protected static ?string $pluralModelLabel = 'Registration Fields';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Field Details')
                    ->description('Create custom fields that will appear on the public registration form.')
                    ->schema([
                        TextInput::make('label')
                            ->label('Field Label')
                            ->placeholder('Example: Dietary Requirement')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (filled($state)) {
                                    $set('name', Str::slug($state, '_'));
                                }
                            }),

                        TextInput::make('name')
                            ->label('Field Key')
                            ->placeholder('dietary_requirement')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Used internally. Use lowercase words separated by underscore.'),

                        Select::make('field_type')
                            ->label('Field Type')
                            ->options([
                                'text' => 'Text',
                                'textarea' => 'Textarea',
                                'email' => 'Email',
                                'phone' => 'Phone',
                                'number' => 'Number',
                                'date' => 'Date',
                                'select' => 'Select Dropdown',
                                'radio' => 'Radio Options',
                                'checkbox' => 'Checkbox Options',
                            ])
                            ->default('text')
                            ->required()
                            ->live(),

                        TextInput::make('placeholder')
                            ->label('Placeholder')
                            ->placeholder('Example: Enter your answer')
                            ->maxLength(255),

                        Textarea::make('help_text')
                            ->label('Help Text')
                            ->placeholder('Example: Tell us if you have any special dietary needs.')
                            ->rows(2)
                            ->columnSpanFull(),

                        KeyValue::make('options')
                            ->label('Options')
                            ->keyLabel('Value')
                            ->valueLabel('Label')
                            ->helperText('Only for select, radio, and checkbox fields. Example value: vip, label: VIP.')
                            ->visible(fn ($get): bool => in_array($get('field_type'), ['select', 'radio', 'checkbox'], true))
                            ->columnSpanFull(),

                        Toggle::make('is_required')
                            ->label('Required')
                            ->default(false),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first.'),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('label')
                    ->label('Label')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Key')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('field_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'text' => 'gray',
                        'textarea' => 'gray',
                        'email' => 'info',
                        'phone' => 'info',
                        'number' => 'warning',
                        'date' => 'primary',
                        'select' => 'success',
                        'radio' => 'success',
                        'checkbox' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'text' => 'Text',
                        'textarea' => 'Textarea',
                        'email' => 'Email',
                        'phone' => 'Phone',
                        'number' => 'Number',
                        'date' => 'Date',
                        'select' => 'Select',
                        'radio' => 'Radio',
                        'checkbox' => 'Checkbox',
                        default => ucfirst((string) $state),
                    }),

                TextColumn::make('placeholder')
                    ->label('Placeholder')
                    ->limit(24)
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_required')
                    ->label('Required')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('field_type')
                    ->label('Field Type')
                    ->options([
                        'text' => 'Text',
                        'textarea' => 'Textarea',
                        'email' => 'Email',
                        'phone' => 'Phone',
                        'number' => 'Number',
                        'date' => 'Date',
                        'select' => 'Select Dropdown',
                        'radio' => 'Radio Options',
                        'checkbox' => 'Checkbox Options',
                    ]),

                SelectFilter::make('is_required')
                    ->label('Required')
                    ->options([
                        1 => 'Required',
                        0 => 'Optional',
                    ]),

                SelectFilter::make('is_active')
                    ->label('Active')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Field')
                    ->icon('heroicon-o-plus')
                    ->color('success'),

                Action::make('add_common_fields')
                    ->label('Add Common Fields')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Add common registration fields?')
                    ->modalDescription('This will add useful fields such as dietary requirement, session selection, emergency contact, and special needs if they do not already exist.')
                    ->action(function (): void {
                        $owner = $this->getOwnerRecord();

                        $fields = [
                            [
                                'label' => 'Dietary Requirement',
                                'name' => 'dietary_requirement',
                                'field_type' => 'textarea',
                                'placeholder' => 'Example: Vegetarian, allergies, no special requirement',
                                'help_text' => 'Tell us if you have any dietary needs.',
                                'is_required' => false,
                                'is_active' => true,
                                'sort_order' => 10,
                            ],
                            [
                                'label' => 'Session Selection',
                                'name' => 'session_selection',
                                'field_type' => 'select',
                                'placeholder' => null,
                                'help_text' => 'Choose your preferred session.',
                                'options' => [
                                    'morning' => 'Morning Session',
                                    'afternoon' => 'Afternoon Session',
                                    'full_day' => 'Full Day',
                                ],
                                'is_required' => false,
                                'is_active' => true,
                                'sort_order' => 20,
                            ],
                            [
                                'label' => 'Emergency Contact',
                                'name' => 'emergency_contact',
                                'field_type' => 'phone',
                                'placeholder' => 'Example: 255712345678',
                                'help_text' => 'Optional emergency contact number.',
                                'is_required' => false,
                                'is_active' => true,
                                'sort_order' => 30,
                            ],
                            [
                                'label' => 'Special Needs',
                                'name' => 'special_needs',
                                'field_type' => 'textarea',
                                'placeholder' => 'Example: Wheelchair access, interpreter, seating assistance',
                                'help_text' => 'Tell us if you need any assistance during the event.',
                                'is_required' => false,
                                'is_active' => true,
                                'sort_order' => 40,
                            ],
                        ];

                        $created = 0;

                        foreach ($fields as $field) {
                            $exists = $owner->registrationFields()
                                ->where('name', $field['name'])
                                ->exists();

                            if ($exists) {
                                continue;
                            }

                            $owner->registrationFields()->create($field);
                            $created++;
                        }

                        Notification::make()
                            ->title('Common fields added')
                            ->body($created . ' field(s) created.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record): bool => ! (bool) $record->is_active)
                    ->action(function ($record): void {
                        $record->update([
                            'is_active' => true,
                        ]);

                        Notification::make()
                            ->title('Field activated')
                            ->success()
                            ->send();
                    }),

                Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->visible(fn ($record): bool => (bool) $record->is_active)
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->update([
                            'is_active' => false,
                        ]);

                        Notification::make()
                            ->title('Field deactivated')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    Action::make('activate_selected')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (Collection $records): void {
                            $records->each->update([
                                'is_active' => true,
                            ]);

                            Notification::make()
                                ->title('Selected fields activated')
                                ->success()
                                ->send();
                        }),

                    Action::make('deactivate_selected')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each->update([
                                'is_active' => false,
                            ]);

                            Notification::make()
                                ->title('Selected fields deactivated')
                                ->success()
                                ->send();
                        }),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }
}