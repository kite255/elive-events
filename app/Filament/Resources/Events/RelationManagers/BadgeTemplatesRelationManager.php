<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Filament\Resources\BadgeTemplates\BadgeTemplateResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BadgeTemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'badgeTemplates';

    protected static ?string $title = 'Badge Templates';

    protected static ?string $modelLabel = 'Badge Template';

    protected static ?string $pluralModelLabel = 'Badge Templates';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Template Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Grid::make(2)
                            ->schema([
                                Select::make('category_id')
                                    ->label('Category')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Optional. Leave empty for all categories.'),

                                Select::make('badge_type_id')
                                    ->label('Badge Type')
                                    ->relationship('badgeType', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Optional. Leave empty for all badge types.'),
                            ]),

                        FileUpload::make('background_image_path')
                            ->label('Badge Background')
                            ->image()
                            ->disk('public')
                            ->directory('badge-template-backgrounds')
                            ->visibility('public')
                            ->helperText('Upload a 420x620 badge background image for best result.'),

                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('badge-template-logos')
                            ->visibility('public'),
                    ]),

                Section::make('Badge Size')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('width')
                                    ->numeric()
                                    ->default(420)
                                    ->required(),

                                TextInput::make('height')
                                    ->numeric()
                                    ->default(620)
                                    ->required(),
                            ]),
                    ]),

                Section::make('Colors')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                ColorPicker::make('background_color')
                                    ->default('#F8FAFC')
                                    ->required(),

                                ColorPicker::make('header_color')
                                    ->default('#161943')
                                    ->required(),

                                ColorPicker::make('accent_color')
                                    ->default('#F99A12')
                                    ->required(),

                                ColorPicker::make('text_color')
                                    ->default('#0B1F3A')
                                    ->required(),

                                ColorPicker::make('footer_color')
                                    ->default('#0B1F3A')
                                    ->required(),
                            ]),
                    ]),

                Section::make('Visible Fields')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Toggle::make('show_category')
                                    ->default(true),

                                Toggle::make('show_badge_type')
                                    ->default(true),

                                Toggle::make('show_badge_number')
                                    ->default(true),

                                Toggle::make('show_organization')
                                    ->default(true),

                                Toggle::make('show_position')
                                    ->default(true),
                            ]),
                    ]),

                Section::make('Status')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_default')
                                    ->label('Default Template')
                                    ->default(false),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Template')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->placeholder('All')
                    ->sortable(),

                TextColumn::make('badgeType.name')
                    ->label('Badge Type')
                    ->badge()
                    ->placeholder('All')
                    ->sortable(),

                TextColumn::make('width')
                    ->label('Width'),

                TextColumn::make('height')
                    ->label('Height'),

                IconColumn::make('background_image_path')
                    ->label('Background')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->background_image_path)),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Badge Template')
                    ->mutateDataUsing(function (array $data): array {
                        $data['event_id'] = $this->getOwnerRecord()->id;

                        return $data;
                    })
                    ->after(function ($record): void {
                        $this->createDefaultElements($record);

                        Notification::make()
                            ->title('Badge template created')
                            ->body('Default badge elements have been created.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('design_badge')
                        ->label('Design Badge')
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->url(fn ($record): string => BadgeTemplateResource::getUrl('design', [
                            'record' => $record,
                        ])),

                    Action::make('preview_badge')
                        ->label('Preview Badge')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn ($record): string => BadgeTemplateResource::getUrl('preview', [
                            'record' => $record,
                        ])),

                    Action::make('create_default_elements')
                        ->label('Create Default Elements')
                        ->icon('heroicon-o-squares-plus')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function ($record): void {
                            $this->createDefaultElements($record, true);

                            Notification::make()
                                ->title('Default elements created')
                                ->success()
                                ->send();
                        }),

                    EditAction::make(),

                    DeleteAction::make(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button()
                    ->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function createDefaultElements($template, bool $replace = false): void
    {
        if ($replace) {
            $template->elements()->delete();
        }

        if ($template->elements()->exists()) {
            return;
        }

        $elements = [
            [
                'type' => 'text',
                'field_key' => 'event_name',
                'label' => 'Event Name',
                'x' => 40,
                'y' => 55,
                'width' => 340,
                'height' => 30,
                'font_size' => 15,
                'font_weight' => '700',
                'color' => '#FFFFFF',
                'text_align' => 'center',
                'sort_order' => 1,
            ],
            [
                'type' => 'text',
                'field_key' => 'full_name',
                'label' => 'Full Name',
                'x' => 40,
                'y' => 250,
                'width' => 340,
                'height' => 40,
                'font_size' => 28,
                'font_weight' => '800',
                'color' => '#0B1F3A',
                'text_align' => 'center',
                'sort_order' => 2,
            ],
            [
                'type' => 'text',
                'field_key' => 'position',
                'label' => 'Position',
                'x' => 40,
                'y' => 290,
                'width' => 340,
                'height' => 30,
                'font_size' => 15,
                'font_weight' => '500',
                'color' => '#475569',
                'text_align' => 'center',
                'sort_order' => 3,
            ],
            [
                'type' => 'text',
                'field_key' => 'organization_name',
                'label' => 'Organization',
                'x' => 40,
                'y' => 320,
                'width' => 340,
                'height' => 30,
                'font_size' => 14,
                'font_weight' => '500',
                'color' => '#64748B',
                'text_align' => 'center',
                'sort_order' => 4,
            ],
            [
                'type' => 'text',
                'field_key' => 'category',
                'label' => 'Category',
                'x' => 80,
                'y' => 365,
                'width' => 260,
                'height' => 35,
                'font_size' => 22,
                'font_weight' => '800',
                'color' => '#161943',
                'background_color' => '#FFFFFF',
                'text_align' => 'center',
                'sort_order' => 5,
            ],
            [
                'type' => 'text',
                'field_key' => 'badge_type',
                'label' => 'Badge Type',
                'x' => 45,
                'y' => 420,
                'width' => 155,
                'height' => 35,
                'font_size' => 14,
                'font_weight' => '800',
                'color' => '#0F172A',
                'background_color' => '#FFFFFF',
                'text_align' => 'center',
                'sort_order' => 6,
            ],
            [
                'type' => 'text',
                'field_key' => 'badge_number',
                'label' => 'Badge Number',
                'x' => 215,
                'y' => 420,
                'width' => 160,
                'height' => 35,
                'font_size' => 10,
                'font_weight' => '800',
                'color' => '#0F172A',
                'background_color' => '#FFFFFF',
                'text_align' => 'center',
                'sort_order' => 7,
            ],
            [
                'type' => 'qr',
                'field_key' => 'qr_code',
                'label' => 'QR Code',
                'x' => 145,
                'y' => 465,
                'width' => 130,
                'height' => 130,
                'sort_order' => 8,
            ],
        ];

        foreach ($elements as $element) {
            $template->elements()->create($element);
        }
    }
}