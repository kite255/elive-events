<?php

namespace App\Livewire\Tickets;

use App\Models\TicketTemplatePage;
use App\Services\Tickets\TicketDesignerService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class TicketDesigner extends Component
{
    use WithFileUploads;

    public string $templateType;

    public int $templateId;

    public string $templateName = '';

    public int $canvasWidth = 1080;

    public int $canvasHeight = 1350;

    public array $pages = [];

    public ?int $activePageId = null;

    public array $elements = [];

    public ?string $selectedElementId = null;

    public bool $isDirty = false;

    public $backgroundUpload = null;

    public ?string $backgroundImagePath = null;

    public function mount(
        string $templateType,
        int $templateId
    ): void {
        $this->templateType = $templateType;
        $this->templateId = $templateId;

        $service = app(
            TicketDesignerService::class
        );

        $template = $service->loadTemplate(
            $templateType,
            $templateId
        );

        $this->templateName =
            (string) $template->name;

        $this->canvasWidth =
            (int) $template->width;

        $this->canvasHeight =
            (int) $template->height;

        $service->ensureInitialPage(
            $templateType,
            $templateId
        );

        $this->reloadPages();

        $firstPage = $this->pages[0] ?? null;

        if ($firstPage === null) {
            return;
        }

        $this->loadPage(
            (int) $firstPage['id']
        );
    }

    public function updatedBackgroundUpload(): void
    {
        $this->resetErrorBag(
            'backgroundUpload'
        );
    }

    public function switchPage(
        int $pageId
    ): void {
        if (! $this->pageBelongsToTemplate(
            $pageId
        )) {
            $this->addError(
                'activePageId',
                'The selected page does not belong to this template.'
            );

            return;
        }

        $this->resetErrorBag(
            'activePageId'
        );

        $this->loadPage(
            $pageId
        );
    }

    public function addPage(
        ?string $name = null
    ): void {
        $service = app(
            TicketDesignerService::class
        );

        $normalizedName =
            $name !== null
                ? trim($name)
                : null;

        if ($normalizedName === '') {
            $normalizedName = null;
        }

        $page = $service->createPage(
            $this->templateType,
            $this->templateId,
            $normalizedName
        );

        $this->reloadPages();

        $this->loadPage(
            $page->id
        );
    }

    public function renameActivePage(
        string $name
    ): void {
        $normalizedName = trim($name);

        if ($normalizedName === '') {
            $this->addError(
                'pageName',
                'The page name is required.'
            );

            return;
        }

        if ($this->activePageId === null) {
            $this->addError(
                'pageName',
                'No active page is selected.'
            );

            return;
        }

        $page = $this->findTemplatePage(
            $this->activePageId
        );

        if ($page === null) {
            $this->addError(
                'pageName',
                'The active page does not belong to this template.'
            );

            return;
        }

        $service = app(
            TicketDesignerService::class
        );

        $service->renamePage(
            $page,
            $normalizedName
        );

        $this->resetErrorBag(
            'pageName'
        );

        $activePageId =
            $this->activePageId;

        $this->reloadPages();

        $this->activePageId =
            $activePageId;
    }

    public function save(): void
    {
        if ($this->activePageId === null) {
            $this->addError(
                'activePageId',
                'No active page is selected.'
            );

            return;
        }

        $page = $this->findTemplatePage(
            $this->activePageId
        );

        if ($page === null) {
            $this->addError(
                'activePageId',
                'The active page does not belong to this template.'
            );

            return;
        }

        $definition = [
            'version' => 1,
            'elements' => array_values(
                $this->elements
            ),
        ];

        $service = app(
            TicketDesignerService::class
        );

        try {
            $savedPage =
                $service->saveDefinition(
                    $page,
                    $definition
                );
        } catch (ValidationException $exception) {
            $message = collect(
                $exception->errors()
            )
                ->flatten()
                ->first();

            $this->addError(
                'definition',
                is_string($message)
                    ? $message
                    : 'The ticket design contains invalid elements.'
            );

            return;
        }

        $this->resetErrorBag(
            'definition'
        );

        $this->resetErrorBag(
            'activePageId'
        );

        $activePageId =
            $savedPage->id;

        $this->reloadPages();

        $this->loadPage(
            $activePageId
        );
    }

    public function uploadBackground(): void
    {
        if ($this->activePageId === null) {
            $this->addError(
                'backgroundUpload',
                'No active page is selected.'
            );

            return;
        }

        $page = $this->findTemplatePage(
            $this->activePageId
        );

        if ($page === null) {
            $this->addError(
                'backgroundUpload',
                'The active page does not belong to this template.'
            );

            return;
        }

        $this->validate([
            'backgroundUpload' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
                'dimensions:max_width=4000,max_height=4000',
            ],
        ]);

        $oldPath =
            $page->background_image_path;

        $newPath =
            $this->backgroundUpload->store(
                'ticket-template-backgrounds',
                'public'
            );

        $page->forceFill([
            'background_image_path' =>
                $newPath,
        ])->save();

        if (
            filled($oldPath)
            && $oldPath !== $newPath
        ) {
            Storage::disk('public')->delete(
                $oldPath
            );
        }

        $this->backgroundImagePath =
            $newPath;

        $this->backgroundUpload =
            null;

        $this->resetErrorBag(
            'backgroundUpload'
        );

        $activePageId =
            $page->id;

        $this->reloadPages();

        $this->loadPage(
            $activePageId
        );
    }

    public function removeBackground(): void
    {
        if ($this->activePageId === null) {
            return;
        }

        $page = $this->findTemplatePage(
            $this->activePageId
        );

        if ($page === null) {
            return;
        }

        $oldPath =
            $page->background_image_path;

        $page->forceFill([
            'background_image_path' =>
                null,
        ])->save();

        if (filled($oldPath)) {
            Storage::disk('public')->delete(
                $oldPath
            );
        }

        $this->backgroundImagePath =
            null;

        $this->backgroundUpload =
            null;

        $this->resetErrorBag(
            'backgroundUpload'
        );

        $activePageId =
            $page->id;

        $this->reloadPages();

        $this->loadPage(
            $activePageId
        );
    }

    public function selectElement(
        ?string $elementId
    ): void {
        if ($elementId === null) {
            $this->selectedElementId = null;

            return;
        }

        $exists = collect(
            $this->elements
        )->contains(
            fn (array $element): bool =>
                ($element['id'] ?? null)
                    === $elementId
        );

        $this->selectedElementId =
            $exists
                ? $elementId
                : null;
    }

    public function addText(): void
    {
        $this->addElement(
            'txt',
            [
                'type' => 'text',
                'binding' => 'holder_name',
                'x' => 100,
                'y' => 100,
                'width' => 500,
                'height' => 100,
                'rotation' => 0,
                'style' => [
                    'fontFamily' => 'Arial',
                    'fontSize' => 48,
                    'fontWeight' => 700,
                    'textAlign' => 'center',
                    'color' => '#161943',
                ],
            ]
        );
    }

    public function addQr(): void
    {
        $this->addElement(
            'qr',
            [
                'type' => 'qr',
                'binding' => 'ticket_qr',
                'x' => 100,
                'y' => 250,
                'width' => 300,
                'height' => 300,
                'rotation' => 0,
            ]
        );
    }

    public function addImage(): void
    {
        $this->addElement(
            'img',
            [
                'type' => 'image',
                'x' => 100,
                'y' => 100,
                'width' => 400,
                'height' => 300,
                'rotation' => 0,
            ]
        );
    }

    public function addLogo(): void
    {
        $this->addElement(
            'logo',
            [
                'type' => 'logo',
                'x' => 100,
                'y' => 100,
                'width' => 250,
                'height' => 250,
                'rotation' => 0,
            ]
        );
    }

    public function addSponsorLogo(): void
    {
        $this->addElement(
            'sponsor',
            [
                'type' => 'sponsor_logo',
                'x' => 100,
                'y' => 100,
                'width' => 250,
                'height' => 150,
                'rotation' => 0,
            ]
        );
    }

    public function addShape(): void
    {
        $this->addElement(
            'shape',
            [
                'type' => 'shape',
                'x' => 100,
                'y' => 100,
                'width' => 400,
                'height' => 200,
                'rotation' => 0,
            ]
        );
    }

    public function addLine(): void
    {
        $this->addElement(
            'line',
            [
                'type' => 'line',
                'x' => 100,
                'y' => 100,
                'width' => 400,
                'height' => 4,
                'rotation' => 0,
            ]
        );
    }

    public function moveSelectedElement(
        int|float $x,
        int|float $y
    ): void {
        $index =
            $this->selectedElementIndex();

        if ($index === null) {
            return;
        }

        $this->elements[$index]['x'] =
            $x;

        $this->elements[$index]['y'] =
            $y;

        $this->isDirty = true;
    }

    public function resizeSelectedElement(
        int|float $width,
        int|float $height
    ): void {
        if (
            $width <= 0
            || $height <= 0
        ) {
            return;
        }

        $index =
            $this->selectedElementIndex();

        if ($index === null) {
            return;
        }

        $this->elements[$index]['width'] =
            $width;

        $this->elements[$index]['height'] =
            $height;

        $this->isDirty = true;
    }

    public function updateSelectedElement(
        array $properties
    ): void {
        $index =
            $this->selectedElementIndex();

        if ($index === null) {
            return;
        }

        $element =
            $this->elements[$index];

        $allowedProperties = [
            'x',
            'y',
            'width',
            'height',
            'rotation',
            'binding',
            'style',
            'asset_id',
            'asset_path',
            'label',
        ];

        foreach (
            $properties
            as $key => $value
        ) {
            if (! in_array(
                $key,
                $allowedProperties,
                true
            )) {
                continue;
            }

            if (
                $key === 'binding'
                && ($element['type'] ?? null)
                    === 'qr'
            ) {
                continue;
            }

            $element[$key] =
                $value;
        }

        if (
            ($element['type'] ?? null)
                === 'qr'
        ) {
            $element['binding'] =
                'ticket_qr';
        }

        $this->elements[$index] =
            $element;

        $this->isDirty = true;
    }

    public function moveLayerForward(): void
    {
        $index =
            $this->selectedElementIndex();

        if ($index === null) {
            return;
        }

        $lastIndex =
            count($this->elements) - 1;

        if ($index >= $lastIndex) {
            return;
        }

        $current =
            $this->elements[$index];

        $next =
            $this->elements[$index + 1];

        $this->elements[$index] =
            $next;

        $this->elements[$index + 1] =
            $current;

        $this->isDirty = true;
    }

    public function moveLayerBackward(): void
    {
        $index =
            $this->selectedElementIndex();

        if ($index === null) {
            return;
        }

        if ($index <= 0) {
            return;
        }

        $current =
            $this->elements[$index];

        $previous =
            $this->elements[$index - 1];

        $this->elements[$index] =
            $previous;

        $this->elements[$index - 1] =
            $current;

        $this->isDirty = true;
    }

    public function deleteSelectedElement(): void
    {
        if ($this->selectedElementId === null) {
            return;
        }

        $originalCount =
            count($this->elements);

        $this->elements =
            array_values(
                array_filter(
                    $this->elements,
                    fn (
                        array $element
                    ): bool =>
                        ($element['id'] ?? null)
                            !== $this->selectedElementId
                )
            );

        $deleted =
            count($this->elements)
                !== $originalCount;

        $this->selectedElementId =
            null;

        if ($deleted) {
            $this->isDirty = true;
        }
    }

    public function backgroundUploadName(): ?string
    {
        if ($this->backgroundUpload === null) {
            return null;
        }

        return $this->backgroundUpload
            ->getClientOriginalName();
    }

    public function backgroundUploadPreviewUrl(): ?string
    {
        if ($this->backgroundUpload === null) {
            return null;
        }

        try {
            $mimeType =
                $this->backgroundUpload
                    ->getMimeType();

            if (! in_array(
                $mimeType,
                [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ],
                true
            )) {
                return null;
            }

            return $this->backgroundUpload
                ->temporaryUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function addElement(
        string $prefix,
        array $element
    ): void {
        $id =
            $this->generateElementId(
                $prefix
            );

        $element = [
            'id' => $id,
            ...$element,
        ];

        $this->elements[] =
            $element;

        $this->selectedElementId =
            $id;

        $this->isDirty =
            true;
    }

    protected function generateElementId(
        string $prefix
    ): string {
        do {
            $id = Str::lower(
                $prefix
                . '_'
                . Str::random(8)
            );

            $exists = collect(
                $this->elements
            )->contains(
                fn (
                    array $element
                ): bool =>
                    ($element['id'] ?? null)
                        === $id
            );
        } while ($exists);

        return $id;
    }

    protected function selectedElementIndex(): ?int
    {
        if (
            $this->selectedElementId
                === null
        ) {
            return null;
        }

        foreach (
            $this->elements
            as $index => $element
        ) {
            if (
                ($element['id'] ?? null)
                    === $this->selectedElementId
            ) {
                return $index;
            }
        }

        return null;
    }

    protected function findTemplatePage(
        int $pageId
    ): ?TicketTemplatePage {
        $service = app(
            TicketDesignerService::class
        );

        return $service->pages(
            $this->templateType,
            $this->templateId
        )->first(
            fn (
                TicketTemplatePage $page
            ): bool =>
                $page->id === $pageId
        );
    }

    protected function reloadPages(): void
    {
        $service = app(
            TicketDesignerService::class
        );

        $this->pages =
            $service->pages(
                $this->templateType,
                $this->templateId
            )
                ->map(
                    fn (
                        TicketTemplatePage $page
                    ): array => [
                        'id' =>
                            $page->id,

                        'page_number' =>
                            $page->page_number,

                        'name' =>
                            $page->name,

                        'background_image_path' =>
                            $page->background_image_path,

                        'definition' =>
                            $page->definition,
                    ]
                )
                ->values()
                ->all();
    }

    protected function loadPage(
        int $pageId
    ): void {
        $page = collect(
            $this->pages
        )->first(
            fn (
                array $page
            ): bool =>
                (int) $page['id']
                    === $pageId
        );

        if ($page === null) {
            return;
        }

        $this->activePageId =
            (int) $page['id'];

        $this->backgroundImagePath =
            filled(
                $page['background_image_path']
                    ?? null
            )
                ? (string) $page[
                    'background_image_path'
                ]
                : null;

        $this->backgroundUpload =
            null;

        $this->resetErrorBag(
            'backgroundUpload'
        );

        $definition =
            is_array(
                $page['definition'] ?? null
            )
                ? $page['definition']
                : [];

        $this->elements =
            is_array(
                $definition['elements']
                    ?? null
            )
                ? array_values(
                    $definition['elements']
                )
                : [];

        $this->selectedElementId =
            null;

        $this->isDirty =
            false;
    }

    protected function pageBelongsToTemplate(
        int $pageId
    ): bool {
        return collect(
            $this->pages
        )->contains(
            fn (
                array $page
            ): bool =>
                (int) $page['id']
                    === $pageId
        );
    }

    public function render()
    {
        return view(
            'livewire.tickets.ticket-designer'
        );
    }
}