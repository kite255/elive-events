<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\EventCommunication;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

abstract class EventCommunicationEditor extends Page
{
    protected static string $resource = EventResource::class;

    protected string $view =
        'filament.resources.events.pages.event-communication-editor';

    public Event $event;

    public ?EventCommunication $communication = null;

    public ?array $data = [];

    protected function mountEditor(
        Event $event,
        ?EventCommunication $communication = null
    ): void {
        abort_unless(
            $event->isAccessibleBy(auth()->user()),
            403
        );

        if (
            $communication
            && (int) $communication->event_id
                !== (int) $event->getKey()
        ) {
            abort(404);
        }

        $this->event = $event;

        $this->communication =
            $communication?->load([
                'sections',
                'links',
                'images',
                'attachments',
            ]);

        $this->form->fill(
            $this->communication
                ? $this->existingState($this->communication)
                : $this->defaultState()
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Communication')
                    ->description(
                        'Create an announcement, highlight, update, schedule, reminder, notice, summary, or custom event communication.'
                    )
                    ->schema([
                        Select::make('type')
                            ->label('Communication Type')
                            ->options([
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
                                    'Custom Communication',
                            ])
                            ->default('announcement')
                            ->required()
                            ->native(false)
                            ->live(),

                        TextInput::make('title')
                            ->label('Title')
                            ->placeholder(
                                "Today's Highlights – 23 August 2026"
                            )
                            ->required()
                            ->maxLength(255)
                            ->live(debounce: 500),

                        Textarea::make('summary')
                            ->label('Short Summary')
                            ->rows(3)
                            ->maxLength(1000)
                            ->live(debounce: 500)
                            ->helperText(
                                'A short introduction for the public page and future SMS / WhatsApp versions.'
                            )
                            ->columnSpanFull(),

                        RichEditor::make('body')
                            ->label('Main Content')
                            ->live(debounce: 750)
                            ->helperText(
                                'Write the main communication content. Use the structured sections below for key highlights or information.'
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Hero Section')
                    ->description(
                        'Optional hero shown at the top of the public communication page.'
                    )
                    ->schema([
                        Toggle::make('hero_enabled')
                            ->label('Enable Hero')
                            ->default(true)
                            ->live(),

                        Toggle::make(
                            'hero_overlay_enabled'
                        )
                            ->label('Dark Image Overlay')
                            ->helperText(
                                'Improves title readability on bright images.'
                            )
                            ->default(true)
                            ->live()
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'hero_enabled'
                                    )
                            ),

                        FileUpload::make(
                            'hero_image_path'
                        )
                            ->label('Hero Image')
                            ->disk('public')
                            ->directory(
                                'event-communications/heroes'
                            )
                            ->image()
                            ->imageEditor()
                            ->imagePreviewHeight('180')
                            ->downloadable()
                            ->openable()
                            ->maxSize(5120)
                            ->live()
                            ->helperText(
                                'Optional. If empty, the event banner is used when available.'
                            )
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'hero_enabled'
                                    )
                            )
                            ->columnSpanFull(),

                        TextInput::make('hero_title')
                            ->label('Hero Title')
                            ->placeholder(
                                "Today's Highlights"
                            )
                            ->maxLength(255)
                            ->live(debounce: 500)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'hero_enabled'
                                    )
                            ),

                        TextInput::make('hero_subtitle')
                            ->label('Hero Subtitle')
                            ->placeholder(
                                '23 August 2026'
                            )
                            ->maxLength(255)
                            ->live(debounce: 500)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'hero_enabled'
                                    )
                            ),

                        Select::make(
                            'hero_text_alignment'
                        )
                            ->label('Text Alignment')
                            ->options([
                                'left' =>
                                    'Left',

                                'center' =>
                                    'Center',
                            ])
                            ->default('left')
                            ->native(false)
                            ->live()
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'hero_enabled'
                                    )
                            ),

                        Select::make('hero_height')
                            ->label('Hero Height')
                            ->options([
                                'small' =>
                                    'Small',

                                'medium' =>
                                    'Medium',

                                'large' =>
                                    'Large',
                            ])
                            ->default('medium')
                            ->native(false)
                            ->live()
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'hero_enabled'
                                    )
                            ),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make(
                    'Highlight / Content Sections'
                )
                    ->description(
                        'Add key highlights or structured information. These cards intentionally do not contain images.'
                    )
                    ->schema([
                        Repeater::make('sections')
                            ->label('Sections')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Section Title')
                                    ->placeholder(
                                        'Example: Camp Meeting Opening'
                                    )
                                    ->required()
                                    ->maxLength(255)
                                    ->live(debounce: 400),

                                RichEditor::make('content')
                                    ->label('Section Content')
                                    ->live(debounce: 650)
                                    ->columnSpanFull(),
                            ])
                            ->columns(1)
                            ->reorderable()
                            ->live()
                            ->collapsible()
                            ->cloneable()
                            ->defaultItems(0)
                            ->addActionLabel('Add Section')
                            ->itemLabel(
                                fn (
                                    array $state
                                ): ?string =>
                                    filled(
                                        $state['title']
                                        ?? null
                                    )
                                        ? $state['title']
                                        : 'New Section'
                            ),
                    ])
                    ->collapsible(),

                Section::make('Quick Links')
                    ->description(
                        'Add useful buttons such as Tomorrow’s Programme, Event Location, Livestream, Photo Gallery, Event Website, or any other attendee link.'
                    )
                    ->schema([
                        Repeater::make('links')
                            ->label('Links')
                            ->schema([
                                TextInput::make('label')
                                    ->label('Button Label')
                                    ->placeholder(
                                        'Example: Tomorrow’s Programme'
                                    )
                                    ->required()
                                    ->maxLength(120)
                                    ->live(debounce: 400),

                                TextInput::make('url')
                                    ->label('URL')
                                    ->placeholder(
                                        'https://example.com or /events/...'
                                    )
                                    ->required()
                                    ->maxLength(2048)
                                    ->live(debounce: 400),

                                Toggle::make(
                                    'open_in_new_tab'
                                )
                                    ->label('Open in New Tab')
                                    ->default(true)
                                    ->live(),
                            ])
                            ->columns(2)
                            ->reorderable()
                            ->live()
                            ->collapsible()
                            ->cloneable()
                            ->defaultItems(0)
                            ->addActionLabel('Add Link')
                            ->itemLabel(
                                fn (
                                    array $state
                                ): ?string =>
                                    filled(
                                        $state['label']
                                        ?? null
                                    )
                                        ? $state['label']
                                        : 'Quick Link'
                            ),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Photo Gallery')
                    ->description(
                        'Upload event photos separately. They will be available from the public communication link.'
                    )
                    ->schema([
                        Repeater::make('images')
                            ->label('Gallery Images')
                            ->schema([
                                FileUpload::make(
                                    'image_path'
                                )
                                    ->label('Image')
                                    ->disk('public')
                                    ->directory(
                                        'event-communications/gallery'
                                    )
                                    ->image()
                                    ->imageEditor()
                                    ->downloadable()
                                    ->openable()
                                    ->maxSize(5120)
                                    ->required()
                                    ->columnSpanFull(),

                                TextInput::make('caption')
                                    ->label('Caption')
                                    ->placeholder(
                                        'Optional description for this photo'
                                    )
                                    ->maxLength(255)
                                    ->live(debounce: 400)
                                    ->columnSpanFull(),
                            ])
                            ->columns(1)
                            ->reorderable()
                            ->live()
                            ->collapsible()
                            ->cloneable()
                            ->defaultItems(0)
                            ->addActionLabel('Add Photo')
                            ->itemLabel(
                                fn (
                                    array $state
                                ): ?string =>
                                    filled(
                                        $state['caption']
                                        ?? null
                                    )
                                        ? $state['caption']
                                        : 'Gallery Photo'
                            ),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make(
                    'Handouts / Attachments (Optional)'
                )
                    ->description(
                        'Add documents only when needed. The public page hides this section when no documents are added.'
                    )
                    ->schema([
                        Repeater::make('attachments')
                            ->label('Handouts')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Document Title')
                                    ->placeholder(
                                        'Example: Camp Meeting Programme'
                                    )
                                    ->required()
                                    ->maxLength(255)
                                    ->live(debounce: 400),

                                FileUpload::make(
                                    'file_path'
                                )
                                    ->label('File')
                                    ->disk('public')
                                    ->directory(
                                        'event-communications/attachments'
                                    )
                                    /*
                                     * Accept normal attachment types broadly.
                                     *
                                     * We intentionally do not use
                                     * acceptedFileTypes() here, so PDFs,
                                     * Office files, images, text files,
                                     * archives, audio, video, and other
                                     * normal handouts are not restricted by
                                     * this Filament field.
                                     */
                                    ->downloadable()
                                    ->openable()
                                    ->maxSize(20480)
                                    ->helperText(
                                        'Maximum 20 MB. Supports documents, PDFs, spreadsheets, presentations, images, archives, audio, video, and other normal files.'
                                    )
                                    ->required(),

                                TextInput::make(
                                    'file_type'
                                )
                                    ->label('File Type')
                                    ->placeholder(
                                        'PDF, DOCX, XLSX, PPTX'
                                    )
                                    ->maxLength(50)
                                    ->live(debounce: 400),
                            ])
                            ->columns(2)
                            ->reorderable()
                            ->live()
                            ->collapsible()
                            ->cloneable()
                            ->defaultItems(0)
                            ->addActionLabel(
                                'Add Handout'
                            )
                            ->itemLabel(
                                fn (
                                    array $state
                                ): ?string =>
                                    filled(
                                        $state['title']
                                        ?? null
                                    )
                                        ? $state['title']
                                        : 'Optional Handout'
                            ),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Publishing')
                    ->description(
                        'Save as draft, publish immediately, schedule for later, or archive the communication.'
                    )
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                EventCommunication::STATUS_DRAFT =>
                                    'Draft',

                                EventCommunication::STATUS_PUBLISHED =>
                                    'Published',

                                EventCommunication::STATUS_SCHEDULED =>
                                    'Scheduled',

                                EventCommunication::STATUS_ARCHIVED =>
                                    'Archived',
                            ])
                            ->default(
                                EventCommunication::STATUS_DRAFT
                            )
                            ->required()
                            ->native(false)
                            ->live(),

                        Toggle::make('is_public')
                            ->label(
                                'Public Page Enabled'
                            )
                            ->helperText(
                                'When enabled and published, attendees can open the communication through its public link.'
                            )
                            ->default(true),

                        DateTimePicker::make(
                            'scheduled_at'
                        )
                            ->label('Scheduled For')
                            ->seconds(false)
                            ->native(false)
                            ->visible(
                                fn (Get $get): bool =>
                                    $get('status')
                                    === EventCommunication::STATUS_SCHEDULED
                            )
                            ->required(
                                fn (Get $get): bool =>
                                    $get('status')
                                    === EventCommunication::STATUS_SCHEDULED
                            ),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(
                    $this->communication
                        ? 'Save Changes'
                        : 'Save Communication'
                )
                ->icon('heroicon-o-check-circle')
                ->color('primary')
                ->action('save'),

            Action::make('publish')
                ->label('Publish Now')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->action('publishNow'),

            Action::make('back')
                ->label('Back to Event')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(
                    fn (): string =>
                        EventResource::getUrl(
                            'edit',
                            [
                                'record' =>
                                    $this->event,
                            ]
                        )
                ),
        ];
    }

    public function save(): void
    {
        $this->persist();
    }

    public function publishNow(): void
    {
        $this->data['status'] =
            EventCommunication::STATUS_PUBLISHED;

        $this->data['is_public'] = true;

        $this->persist(
            forcePublished: true
        );
    }

    protected function persist(
        bool $forcePublished = false
    ): void {
        $data =
            $this->form->getState();

        if ($forcePublished) {
            $data['status'] =
                EventCommunication::STATUS_PUBLISHED;

            $data['is_public'] = true;
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize RichEditor values
        |--------------------------------------------------------------------------
        |
        | Filament RichEditor may expose live state as a structured array.
        | The database columns are text / longText, so normalize the values
        | before persisting them.
        |
        */

        $data['body'] =
            $this->normalizeRichContent(
                $data['body']
                ?? null
            );

        $sections =
            array_values(
                $data['sections']
                ?? []
            );

        foreach (
            $sections
            as $index => $section
        ) {
            $sections[$index]['content'] =
                $this->normalizeRichContent(
                    $section['content']
                    ?? null
                );
        }

        $images =
            array_values(
                $data['images']
                ?? []
            );

        $links =
            array_values(
                $data['links']
                ?? []
            );

        $attachments =
            array_values(
                $data['attachments']
                ?? []
            );

        unset(
            $data['sections'],
            $data['links'],
            $data['images'],
            $data['attachments']
        );

        $wasNew =
            $this->communication === null;

        DB::transaction(
            function () use (
                $data,
                $sections,
                $links,
                $images,
                $attachments
            ): void {
                $communication =
                    $this->communication
                    ?? new EventCommunication();

                $communication->fill(
                    $data
                );

                $communication->event_id =
                    $this->event->getKey();

                if (! $communication->exists) {
                    $communication->created_by =
                        auth()->id();
                }

                if (
                    $communication->status
                    === EventCommunication::STATUS_PUBLISHED
                ) {
                    $communication->published_at =
                        $communication->published_at
                        ?? now();

                    $communication->scheduled_at =
                        null;
                } elseif (
                    $communication->status
                    !== EventCommunication::STATUS_SCHEDULED
                ) {
                    $communication->scheduled_at =
                        null;
                }

                if (
                    $communication->status
                    === EventCommunication::STATUS_ARCHIVED
                ) {
                    $communication->is_public =
                        false;
                }

                $communication->save();

                $this->syncSections(
                    $communication,
                    $sections
                );

                $this->syncLinks(
                    $communication,
                    $links
                );

                $this->syncImages(
                    $communication,
                    $images
                );

                $this->syncAttachments(
                    $communication,
                    $attachments
                );

                $this->communication =
                    $communication->fresh([
                        'sections',
                        'links',
                        'images',
                        'attachments',
                    ]);
            }
        );

        Notification::make()
            ->title(
                $this->communication?->status
                    === EventCommunication::STATUS_PUBLISHED
                        ? 'Communication published'
                        : 'Communication saved'
            )
            ->body(
                $this->communication?->title
            )
            ->success()
            ->send();

        if ($wasNew) {
            $this->redirect(
                EventResource::getUrl(
                    'communication-edit',
                    [
                        'event' =>
                            $this->event,

                        'communication' =>
                            $this->communication,
                    ]
                ),
                navigate: true
            );
        }
    }

    protected function syncSections(
        EventCommunication $communication,
        array $sections
    ): void {
        $communication
            ->sections()
            ->delete();

        foreach (
            $sections
            as $index => $section
        ) {
            $title =
                trim(
                    (string) (
                        $section['title']
                        ?? ''
                    )
                );

            if ($title === '') {
                continue;
            }

            $communication
                ->sections()
                ->create([
                    'title' =>
                        $title,

                    'content' =>
                        $this->normalizeRichContent(
                            $section['content']
                            ?? null
                        ),

                    'sort_order' =>
                        $index,
                ]);
        }
    }

    protected function syncLinks(
        EventCommunication $communication,
        array $links
    ): void {
        $communication
            ->links()
            ->delete();

        foreach (
            $links
            as $index => $link
        ) {
            $label =
                trim(
                    (string) (
                        $link['label']
                        ?? ''
                    )
                );

            $url =
                $this->normalizeLinkUrl(
                    $link['url']
                    ?? null
                );

            if (
                $label === ''
                || ! $url
            ) {
                continue;
            }

            $communication
                ->links()
                ->create([
                    'label' =>
                        $label,

                    'url' =>
                        $url,

                    'open_in_new_tab' =>
                        (bool) (
                            $link[
                                'open_in_new_tab'
                            ]
                            ?? true
                        ),

                    'sort_order' =>
                        $index,
                ]);
        }
    }

    protected function syncImages(
        EventCommunication $communication,
        array $images
    ): void {
        $communication
            ->images()
            ->delete();

        foreach (
            $images
            as $index => $image
        ) {
            $path =
                $this->normalizeFilePath(
                    $image['image_path']
                    ?? null
                );

            if (! $path) {
                continue;
            }

            $communication
                ->images()
                ->create([
                    'image_path' =>
                        $path,

                    'caption' =>
                        filled(
                            $image['caption']
                            ?? null
                        )
                            ? trim(
                                (string)
                                $image['caption']
                            )
                            : null,

                    'sort_order' =>
                        $index,
                ]);
        }
    }

    protected function syncAttachments(
        EventCommunication $communication,
        array $attachments
    ): void {
        $communication
            ->attachments()
            ->delete();

        foreach (
            $attachments
            as $index => $attachment
        ) {
            $path =
                $this->normalizeFilePath(
                    $attachment['file_path']
                    ?? null
                );

            if (! $path) {
                continue;
            }

            $title =
                trim(
                    (string) (
                        $attachment['title']
                        ?? ''
                    )
                );

            if ($title === '') {
                continue;
            }

            $fileType =
                filled(
                    $attachment['file_type']
                    ?? null
                )
                    ? strtoupper(
                        trim(
                            (string)
                            $attachment['file_type']
                        )
                    )
                    : strtoupper(
                        pathinfo(
                            $path,
                            PATHINFO_EXTENSION
                        )
                    );

            $communication
                ->attachments()
                ->create([
                    'title' =>
                        $title,

                    'file_path' =>
                        $path,

                    'file_type' =>
                        $fileType ?: null,

                    'sort_order' =>
                        $index,
                ]);
        }
    }

    protected function normalizeRichContent(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value !== ''
                ? $value
                : null;
        }

        if (! is_array($value)) {
            $value = trim(
                (string) $value
            );

            return $value !== ''
                ? $value
                : null;
        }

        $text =
            $this->extractRichText(
                $value
            );

        $text = trim($text);

        if ($text === '') {
            return null;
        }

        return nl2br(
            e($text)
        );
    }

    protected function extractRichText(
        mixed $value
    ): string {
        if (! is_array($value)) {
            return '';
        }

        $parts = [];

        /*
         * TipTap / Filament RichEditor structure contains metadata such as:
         * type => doc, paragraph, text, etc.
         *
         * Only values stored under the "text" key are real user content.
         */
        if (
            array_key_exists(
                'text',
                $value
            )
            && is_string(
                $value['text']
            )
        ) {
            $text = trim(
                $value['text']
            );

            if ($text !== '') {
                $parts[] = $text;
            }
        }

        foreach (
            $value
            as $key => $child
        ) {
            if ($key === 'text') {
                continue;
            }

            if (! is_array($child)) {
                continue;
            }

            $childText =
                trim(
                    $this->extractRichText(
                        $child
                    )
                );

            if ($childText !== '') {
                $parts[] =
                    $childText;
            }
        }

        return implode(
            "
",
            $parts
        );
    }

    protected function normalizeLinkUrl(
        mixed $value
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        if (
            str_starts_with(
                strtolower($url),
                'mailto:'
            )
            || str_starts_with(
                strtolower($url),
                'tel:'
            )
        ) {
            return $url;
        }

        if (! preg_match(
            '/^https?:\/\//i',
            $url
        )) {
            $url = 'https://' . $url;
        }

        return filter_var(
            $url,
            FILTER_VALIDATE_URL
        )
            ? $url
            : null;
    }

    protected function normalizeFilePath(
        mixed $value
    ): ?string {
        if (is_string($value)) {
            $value = trim($value);

            return $value !== ''
                ? $value
                : null;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $path =
                    $this->normalizeFilePath(
                        $item
                    );

                if ($path) {
                    return $path;
                }
            }
        }

        return null;
    }

    protected function existingState(
        EventCommunication $communication
    ): array {
        return [
            'type' =>
                $communication->type,

            'title' =>
                $communication->title,

            'summary' =>
                $communication->summary,

            'body' =>
                $communication->body,

            'status' =>
                $communication->status,

            'is_public' =>
                (bool)
                $communication->is_public,

            'scheduled_at' =>
                $communication->scheduled_at,

            'hero_enabled' =>
                (bool)
                $communication->hero_enabled,

            'hero_image_path' =>
                $communication->hero_image_path,

            'hero_title' =>
                $communication->hero_title,

            'hero_subtitle' =>
                $communication->hero_subtitle,

            'hero_overlay_enabled' =>
                (bool)
                $communication
                    ->hero_overlay_enabled,

            'hero_text_alignment' =>
                $communication
                    ->hero_text_alignment,

            'hero_height' =>
                $communication->hero_height,

            'sections' =>
                $communication
                    ->sections
                    ->map(
                        fn ($section): array => [
                            'title' =>
                                $section->title,

                            'content' =>
                                $section->content,
                        ]
                    )
                    ->all(),

            'links' =>
                $communication
                    ->links
                    ->map(
                        fn ($link): array => [
                            'label' =>
                                $link->label,

                            'url' =>
                                $link->url,

                            'open_in_new_tab' =>
                                (bool)
                                $link
                                    ->open_in_new_tab,
                        ]
                    )
                    ->all(),

            'images' =>
                $communication
                    ->images
                    ->map(
                        fn ($image): array => [
                            'image_path' =>
                                $image->image_path,

                            'caption' =>
                                $image->caption,
                        ]
                    )
                    ->all(),

            'attachments' =>
                $communication
                    ->attachments
                    ->map(
                        fn ($attachment): array => [
                            'title' =>
                                $attachment->title,

                            'file_path' =>
                                $attachment->file_path,

                            'file_type' =>
                                $attachment->file_type,
                        ]
                    )
                    ->all(),
        ];
    }

    protected function defaultState(): array
    {
        return [
            'type' =>
                'announcement',

            'title' =>
                null,

            'summary' =>
                null,

            'body' =>
                null,

            'status' =>
                EventCommunication::STATUS_DRAFT,

            'is_public' =>
                true,

            'scheduled_at' =>
                null,

            'hero_enabled' =>
                true,

            'hero_image_path' =>
                null,

            'hero_title' =>
                null,

            'hero_subtitle' =>
                null,

            'hero_overlay_enabled' =>
                true,

            'hero_text_alignment' =>
                'left',

            'hero_height' =>
                'medium',

            'sections' =>
                [],

            'links' =>
                [],

            'images' =>
                [],

            'attachments' =>
                [],
        ];
    }

    public function getTitle(): string
    {
        return $this->communication
            ? 'Edit Event Communication'
            : 'Create Event Communication';
    }

    public function getSubheading(): ?string
    {
        return $this->event->name;
    }
}
