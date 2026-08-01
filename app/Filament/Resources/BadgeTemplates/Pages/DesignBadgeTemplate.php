<?php

namespace App\Filament\Resources\BadgeTemplates\Pages;

use App\Filament\Resources\BadgeTemplates\BadgeTemplateResource;
use App\Filament\Resources\Events\EventResource;
use App\Models\BadgeTemplate;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DesignBadgeTemplate extends Page
{
    protected static string $resource = BadgeTemplateResource::class;

    protected string $view = 'filament.resources.badge-templates.pages.design-badge-template';

    public BadgeTemplate $record;

    public ?array $data = [];

    public function mount(BadgeTemplate $record): void
    {
        $this->record = $record;

        $config = $this->normalizeDesignConfig(
            $record->design_config ?? $record->getDesignConfigWithDefaults()
        );

        $this->form->fill([
            'background_image_path' => $record->background_image_path,
            'width' => data_get($config, 'canvas.width', $record->width ?? 420),
            'height' => data_get($config, 'canvas.height', $record->height ?? 620),

            'enabled_elements' => $this->resolveEnabledElements($config),

            'name_x' => data_get($config, 'name.x', 210),
            'name_y' => data_get($config, 'name.y', 250),
            'name_font_size' => data_get($config, 'name.font_size', 30),
            'name_color' => data_get($config, 'name.color', '#FFFFFF'),

            'category_x' => data_get($config, 'category.x', 210),
            'category_y' => data_get($config, 'category.y', 315),
            'category_font_size' => data_get($config, 'category.font_size', 18),
            'category_color' => data_get($config, 'category.color', '#FFFFFF'),
            'category_background' => data_get($config, 'category.background', '#F99A12'),

            'organization_x' => data_get($config, 'organization.x', 210),
            'organization_y' => data_get($config, 'organization.y', 360),
            'organization_font_size' => data_get($config, 'organization.font_size', 14),
            'organization_color' => data_get($config, 'organization.color', '#DBEAFE'),

            'position_x' => data_get($config, 'position.x', 210),
            'position_y' => data_get($config, 'position.y', 385),
            'position_font_size' => data_get($config, 'position.font_size', 13),
            'position_color' => data_get($config, 'position.color', '#E0F2FE'),

            'badge_number_x' => data_get($config, 'badge_number.x', 210),
            'badge_number_y' => data_get($config, 'badge_number.y', 420),
            'badge_number_font_size' => data_get($config, 'badge_number.font_size', 13),
            'badge_number_color' => data_get($config, 'badge_number.color', '#FFFFFF'),

            'qr_code_x' => data_get($config, 'qr_code.x', 150),
            'qr_code_y' => data_get($config, 'qr_code.y', 465),
            'qr_code_size' => data_get($config, 'qr_code.size', 120),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Hidden::make('name_x')->default(210),
                Hidden::make('name_y')->default(250),
                Hidden::make('category_x')->default(210),
                Hidden::make('category_y')->default(315),
                Hidden::make('organization_x')->default(210),
                Hidden::make('organization_y')->default(360),
                Hidden::make('position_x')->default(210),
                Hidden::make('position_y')->default(385),
                Hidden::make('badge_number_x')->default(210),
                Hidden::make('badge_number_y')->default(420),
                Hidden::make('qr_code_x')->default(150),
                Hidden::make('qr_code_y')->default(465),

                Section::make('Canvas Settings')
                    ->description('Upload badge background and set badge size.')
                    ->schema([
                        FileUpload::make('background_image_path')
                            ->label('Badge Background')
                            ->disk('public')
                            ->directory('badge-template-backgrounds')
                            ->image()
                            ->visibility('public')
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(5120)
                            ->previewable(true)
                            ->imagePreviewHeight('180')
                            ->downloadable()
                            ->openable()
                            ->preserveFilenames(false)
                            ->helperText('Recommended size: 420px × 620px.'),

                        Grid::make(2)->schema([
                            TextInput::make('width')
                                ->label('Width')
                                ->numeric()
                                ->default(420)
                                ->required()
                                ->suffix('px'),

                            TextInput::make('height')
                                ->label('Height')
                                ->numeric()
                                ->default(620)
                                ->required()
                                ->suffix('px'),
                        ]),
                    ]),

                Section::make('Element Library')
                    ->description('Choose which badge elements should appear on this template. Position them by dragging on the preview.')
                    ->schema([
                        CheckboxList::make('enabled_elements')
                            ->label('Visible Elements')
                            ->options($this->elementOptions())
                            ->columns(2)
                            ->bulkToggleable()
                            ->default(array_keys($this->elementOptions()))
                            ->helperText('Uncheck an element to hide it from the badge. You can re-enable it anytime.'),
                    ])
                    ->collapsible(),

                Section::make('Selected Element Settings')
                    ->description('Select an element from the badge preview to edit its style.')
                    ->schema([])
                    ->extraAttributes([
                        'x-show' => '! selectedKey',
                        'x-cloak' => 'x-cloak',
                    ]),

                Section::make('Attendee Name Settings')
                    ->description('Style the attendee name. Drag it on the preview to change position.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name_font_size')
                                ->label('Font Size')
                                ->numeric()
                                ->required(),

                            ColorPicker::make('name_color')
                                ->label('Color'),
                        ]),
                    ])
                    ->extraAttributes([
                        'x-show' => "selectedKey === 'name'",
                        'x-cloak' => 'x-cloak',
                    ]),

                Section::make('Category Settings')
                    ->description('Style the category label. Drag it on the preview to change position.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('category_font_size')
                                ->label('Font Size')
                                ->numeric()
                                ->required(),

                            ColorPicker::make('category_color')
                                ->label('Text Color'),

                            ColorPicker::make('category_background')
                                ->label('Background'),
                        ]),
                    ])
                    ->extraAttributes([
                        'x-show' => "selectedKey === 'category'",
                        'x-cloak' => 'x-cloak',
                    ]),

                Section::make('Organization Settings')
                    ->description('Style the organization name. Drag it on the preview to change position.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('organization_font_size')
                                ->label('Font Size')
                                ->numeric()
                                ->required(),

                            ColorPicker::make('organization_color')
                                ->label('Color'),
                        ]),
                    ])
                    ->extraAttributes([
                        'x-show' => "selectedKey === 'organization'",
                        'x-cloak' => 'x-cloak',
                    ]),

                Section::make('Position / Title Settings')
                    ->description('Style the attendee position. Drag it on the preview to change position.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('position_font_size')
                                ->label('Font Size')
                                ->numeric()
                                ->required(),

                            ColorPicker::make('position_color')
                                ->label('Color'),
                        ]),
                    ])
                    ->extraAttributes([
                        'x-show' => "selectedKey === 'position'",
                        'x-cloak' => 'x-cloak',
                    ]),

                Section::make('Badge Number Settings')
                    ->description('Style the badge number. Drag it on the preview to change position.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('badge_number_font_size')
                                ->label('Font Size')
                                ->numeric()
                                ->required(),

                            ColorPicker::make('badge_number_color')
                                ->label('Color'),
                        ]),
                    ])
                    ->extraAttributes([
                        'x-show' => "selectedKey === 'badge_number'",
                        'x-cloak' => 'x-cloak',
                    ]),

                Section::make('QR Code Settings')
                    ->description('Set QR code size. Drag it on the preview to change position.')
                    ->schema([
                        Grid::make(1)->schema([
                            TextInput::make('qr_code_size')
                                ->label('Size')
                                ->numeric()
                                ->required()
                                ->suffix('px'),
                        ]),
                    ])
                    ->extraAttributes([
                        'x-show' => "selectedKey === 'qr_code'",
                        'x-cloak' => 'x-cloak',
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_default_elements')
                ->label('Add Default Elements')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->action('addDefaultElements'),

            Action::make('save_design')
                ->label('Save Design')
                ->icon('heroicon-o-check-circle')
                ->color('primary')
                ->action('saveDesign'),

            Action::make('reset_design')
                ->label('Reset Layout')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reset badge layout?')
                ->modalDescription('This will reset the badge element positions to the recommended default layout. Click Save Design afterward to keep the reset.')
                ->action('resetDesign'),

            Action::make('preview_badge')
                ->label('Preview Badge')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn (): string => BadgeTemplateResource::getUrl('preview', [
                    'record' => $this->record,
                ])),

            Action::make('back_to_event')
                ->label('Back to Event')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->visible(fn (): bool => filled($this->record->event_id))
                ->url(fn (): string => EventResource::getUrl('edit', [
                    'record' => $this->record->event_id,
                ])),

            Action::make('back')
                ->label('Back to Templates')
                ->color('gray')
                ->url(BadgeTemplateResource::getUrl('index')),
        ];
    }

    public function addDefaultElements(): void
    {
        $data = $this->data ?? [];

        $this->form->fill(array_merge($data, [
            'enabled_elements' => array_keys($this->elementOptions()),

            'name_x' => 210,
            'name_y' => 250,
            'name_font_size' => $data['name_font_size'] ?? 30,
            'name_color' => $data['name_color'] ?? '#FFFFFF',

            'category_x' => 210,
            'category_y' => 315,
            'category_font_size' => $data['category_font_size'] ?? 18,
            'category_color' => $data['category_color'] ?? '#FFFFFF',
            'category_background' => $data['category_background'] ?? '#F99A12',

            'organization_x' => 210,
            'organization_y' => 360,
            'organization_font_size' => $data['organization_font_size'] ?? 14,
            'organization_color' => $data['organization_color'] ?? '#DBEAFE',

            'position_x' => 210,
            'position_y' => 385,
            'position_font_size' => $data['position_font_size'] ?? 13,
            'position_color' => $data['position_color'] ?? '#E0F2FE',

            'badge_number_x' => 210,
            'badge_number_y' => 420,
            'badge_number_font_size' => $data['badge_number_font_size'] ?? 13,
            'badge_number_color' => $data['badge_number_color'] ?? '#FFFFFF',

            'qr_code_x' => 150,
            'qr_code_y' => 465,
            'qr_code_size' => $data['qr_code_size'] ?? 120,
        ]));

        Notification::make()
            ->title('Default elements added')
            ->body('The default badge elements were added. Drag them on the preview and click Save Design.')
            ->success()
            ->send();
    }

    public function resetDesign(): void
    {
        $this->form->fill([
            'background_image_path' => $this->record->background_image_path,
            'width' => $this->record->width ?? 420,
            'height' => $this->record->height ?? 620,
            'enabled_elements' => array_keys($this->elementOptions()),

            'name_x' => 210,
            'name_y' => 250,
            'name_font_size' => 30,
            'name_color' => '#FFFFFF',

            'category_x' => 210,
            'category_y' => 315,
            'category_font_size' => 18,
            'category_color' => '#FFFFFF',
            'category_background' => '#F99A12',

            'organization_x' => 210,
            'organization_y' => 360,
            'organization_font_size' => 14,
            'organization_color' => '#DBEAFE',

            'position_x' => 210,
            'position_y' => 385,
            'position_font_size' => 13,
            'position_color' => '#E0F2FE',

            'badge_number_x' => 210,
            'badge_number_y' => 420,
            'badge_number_font_size' => 13,
            'badge_number_color' => '#FFFFFF',

            'qr_code_x' => 150,
            'qr_code_y' => 465,
            'qr_code_size' => 120,
        ]);

        Notification::make()
            ->title('Layout reset')
            ->body('The badge layout was reset. Click Save Design to keep the changes.')
            ->warning()
            ->send();
    }

    public function saveDesign(): void
    {
        $data = $this->form->getState();

        $backgroundImagePath = $this->normalizeFileUploadPath($data['background_image_path'] ?? null);
        $enabledElements = $this->normalizeEnabledElements($data['enabled_elements'] ?? []);

        $fixedConfig = $this->buildFixedConfig($data, $enabledElements);
        $flexibleElements = $this->buildFlexibleElements($data, $enabledElements);

        $this->record->update([
            'background_image_path' => $backgroundImagePath,
            'width' => (int) ($data['width'] ?? 420),
            'height' => (int) ($data['height'] ?? 620),
            'design_config' => array_merge($fixedConfig, [
                'canvas' => [
                    'width' => (int) ($data['width'] ?? 420),
                    'height' => (int) ($data['height'] ?? 620),
                    'background_image_path' => $backgroundImagePath,
                ],
                'enabled_elements' => $enabledElements,
                'elements' => $flexibleElements,
            ]),
        ]);

        Notification::make()
            ->title('Badge design saved')
            ->body('The badge design settings were saved successfully.')
            ->success()
            ->send();
    }

    public function getPreviewConfigProperty(): array
    {
        $data = $this->data ?? [];

        $backgroundImagePath = $this->normalizeFileUploadPath(
            $data['background_image_path'] ?? $this->record->background_image_path
        );

        $enabledElements = $this->normalizeEnabledElements(
            $data['enabled_elements'] ?? array_keys($this->elementOptions())
        );

        return array_merge($this->buildFixedConfig($data, $enabledElements), [
            'width' => (int) ($data['width'] ?? 420),
            'height' => (int) ($data['height'] ?? 620),
            'background_image_path' => $backgroundImagePath,
            'enabled_elements' => $enabledElements,
            'elements' => $this->buildFlexibleElements($data, $enabledElements),
        ]);
    }

    protected function normalizeDesignConfig(mixed $config): array
    {
        if (is_string($config)) {
            $config = json_decode($config, true) ?: [];
        }

        if (! is_array($config)) {
            return [];
        }

        if (isset($config['elements']) && is_array($config['elements'])) {
            $fixed = [];

            foreach ($config['elements'] as $element) {
                $key = $this->fixedKeyFromElementType(data_get($element, 'type'));

                if (! $key) {
                    continue;
                }

                $fixed[$key] = [
                    'x' => (int) data_get($element, 'x', $this->defaultElement($key)['x']),
                    'y' => (int) data_get($element, 'y', $this->defaultElement($key)['y']),
                    'font_size' => (int) data_get($element, 'font_size', $this->defaultElement($key)['font_size'] ?? 14),
                    'color' => data_get($element, 'color', $this->defaultElement($key)['color'] ?? '#FFFFFF'),
                    'background' => data_get($element, 'background', $this->defaultElement($key)['background'] ?? null),
                    'size' => (int) data_get($element, 'size', $this->defaultElement($key)['size'] ?? 120),
                    'visible' => (bool) data_get($element, 'visible', true),
                ];
            }

            return array_replace_recursive($config, $fixed);
        }

        return $config;
    }

    protected function resolveEnabledElements(array $config): array
    {
        if (isset($config['enabled_elements']) && is_array($config['enabled_elements'])) {
            return $this->normalizeEnabledElements($config['enabled_elements']);
        }

        $enabled = [];

        foreach (array_keys($this->elementOptions()) as $key) {
            if ((bool) data_get($config, "{$key}.visible", true)) {
                $enabled[] = $key;
            }
        }

        return $enabled;
    }

    protected function normalizeEnabledElements(array $enabledElements): array
    {
        $validKeys = array_keys($this->elementOptions());

        return array_values(array_intersect($enabledElements, $validKeys));
    }

    protected function buildFixedConfig(array $data, array $enabledElements): array
    {
        return [
            'name' => [
                'x' => (int) ($data['name_x'] ?? 210),
                'y' => (int) ($data['name_y'] ?? 250),
                'font_size' => (int) ($data['name_font_size'] ?? 30),
                'font_weight' => 'bold',
                'color' => $data['name_color'] ?? '#FFFFFF',
                'align' => 'center',
                'visible' => in_array('name', $enabledElements, true),
            ],
            'category' => [
                'x' => (int) ($data['category_x'] ?? 210),
                'y' => (int) ($data['category_y'] ?? 315),
                'font_size' => (int) ($data['category_font_size'] ?? 18),
                'font_weight' => 'bold',
                'color' => $data['category_color'] ?? '#FFFFFF',
                'background' => $data['category_background'] ?? '#F99A12',
                'align' => 'center',
                'visible' => in_array('category', $enabledElements, true),
            ],
            'organization' => [
                'x' => (int) ($data['organization_x'] ?? 210),
                'y' => (int) ($data['organization_y'] ?? 360),
                'font_size' => (int) ($data['organization_font_size'] ?? 14),
                'font_weight' => '600',
                'color' => $data['organization_color'] ?? '#DBEAFE',
                'align' => 'center',
                'visible' => in_array('organization', $enabledElements, true),
            ],
            'position' => [
                'x' => (int) ($data['position_x'] ?? 210),
                'y' => (int) ($data['position_y'] ?? 385),
                'font_size' => (int) ($data['position_font_size'] ?? 13),
                'font_weight' => '400',
                'color' => $data['position_color'] ?? '#E0F2FE',
                'align' => 'center',
                'visible' => in_array('position', $enabledElements, true),
            ],
            'badge_number' => [
                'x' => (int) ($data['badge_number_x'] ?? 210),
                'y' => (int) ($data['badge_number_y'] ?? 420),
                'font_size' => (int) ($data['badge_number_font_size'] ?? 13),
                'font_weight' => 'bold',
                'color' => $data['badge_number_color'] ?? '#FFFFFF',
                'align' => 'center',
                'visible' => in_array('badge_number', $enabledElements, true),
            ],
            'qr_code' => [
                'x' => (int) ($data['qr_code_x'] ?? 150),
                'y' => (int) ($data['qr_code_y'] ?? 465),
                'size' => (int) ($data['qr_code_size'] ?? 120),
                'visible' => in_array('qr_code', $enabledElements, true),
            ],
        ];
    }

    protected function buildFlexibleElements(array $data, array $enabledElements): array
    {
        $elements = [];

        foreach (array_keys($this->elementOptions()) as $index => $key) {
            if (! in_array($key, $enabledElements, true)) {
                continue;
            }

            $default = $this->defaultElement($key);

            $element = [
                'id' => $key . '_001',
                'type' => $this->elementTypeFromKey($key),
                'key' => $key,
                'label' => $this->elementOptions()[$key],
                'x' => (int) ($data[$key . '_x'] ?? $default['x']),
                'y' => (int) ($data[$key . '_y'] ?? $default['y']),
                'visible' => true,
                'z_index' => ($index + 1) * 10,
            ];

            if ($key === 'qr_code') {
                $element['size'] = (int) ($data['qr_code_size'] ?? 120);
            } elseif ($key === 'category') {
                $element['font_size'] = (int) ($data['category_font_size'] ?? 18);
                $element['font_weight'] = '800';
                $element['color'] = $data['category_color'] ?? '#FFFFFF';
                $element['background'] = $data['category_background'] ?? '#F99A12';
                $element['align'] = 'center';
                $element['width'] = 230;
                $element['height'] = 38;
            } else {
                $element['font_size'] = (int) ($data[$key . '_font_size'] ?? $default['font_size']);
                $element['font_weight'] = $default['font_weight'];
                $element['color'] = $data[$key . '_color'] ?? $default['color'];
                $element['align'] = 'center';
                $element['width'] = 360;
            }

            $elements[] = $element;
        }

        return $elements;
    }

    protected function defaultElement(string $key): array
    {
        return match ($key) {
            'name' => [
                'x' => 210,
                'y' => 250,
                'font_size' => 30,
                'font_weight' => '800',
                'color' => '#FFFFFF',
            ],
            'category' => [
                'x' => 210,
                'y' => 315,
                'font_size' => 18,
                'font_weight' => '800',
                'color' => '#FFFFFF',
                'background' => '#F99A12',
            ],
            'organization' => [
                'x' => 210,
                'y' => 360,
                'font_size' => 14,
                'font_weight' => '600',
                'color' => '#DBEAFE',
            ],
            'position' => [
                'x' => 210,
                'y' => 385,
                'font_size' => 13,
                'font_weight' => '500',
                'color' => '#E0F2FE',
            ],
            'badge_number' => [
                'x' => 210,
                'y' => 420,
                'font_size' => 13,
                'font_weight' => '800',
                'color' => '#FFFFFF',
            ],
            'qr_code' => [
                'x' => 150,
                'y' => 465,
                'size' => 120,
            ],
            default => [
                'x' => 210,
                'y' => 300,
                'font_size' => 14,
                'font_weight' => '600',
                'color' => '#FFFFFF',
            ],
        };
    }

    protected function elementOptions(): array
    {
        return [
            'name' => 'Attendee Name',
            'category' => 'Category',
            'organization' => 'Organization',
            'position' => 'Position / Title',
            'badge_number' => 'Badge Number',
            'qr_code' => 'QR Code',
        ];
    }

    protected function elementTypeFromKey(string $key): string
    {
        return match ($key) {
            'name' => 'attendee_name',
            'category' => 'category',
            'organization' => 'organization',
            'position' => 'position',
            'badge_number' => 'badge_number',
            'qr_code' => 'qr_code',
            default => $key,
        };
    }

    protected function fixedKeyFromElementType(?string $type): ?string
    {
        return match ($type) {
            'attendee_name', 'name', 'full_name' => 'name',
            'category' => 'category',
            'organization', 'organization_name' => 'organization',
            'position' => 'position',
            'badge_number' => 'badge_number',
            'qr_code' => 'qr_code',
            default => null,
        };
    }

    protected function normalizeFileUploadPath(mixed $path): ?string
    {
        if (is_array($path)) {
            return collect($path)->filter()->first();
        }

        return filled($path) ? (string) $path : null;
    }
}
