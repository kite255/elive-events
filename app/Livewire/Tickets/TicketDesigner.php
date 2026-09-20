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

    public $manualWidth = null;

    public $manualHeight = null;

    public bool $isDirty = false;

    public $backgroundUpload = null;

    public ?string $backgroundImagePath = null;

    public $elementImageUpload = null;

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

    public function updatedElementImageUpload(): void
    {
        $this->resetErrorBag(
            'elementImageUpload'
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

    public function save(): bool
    {
        if ($this->activePageId === null) {
            $this->addError(
                'activePageId',
                'No active page is selected.'
            );

            return false;
        }

        $page = $this->findTemplatePage(
            $this->activePageId
        );

        if ($page === null) {
            $this->addError(
                'activePageId',
                'The active page does not belong to this template.'
            );

            return false;
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

            return false;
        }

        $this->resetErrorBag(
            'definition'
        );

        $this->resetErrorBag(
            'activePageId'
        );

        $activePageId = $savedPage->id;

        $selectedElementId =
            $this->selectedElementId;

        $this->reloadPages();

        $this->loadPage(
            $activePageId
        );

        $this->selectElement(
            $selectedElementId
        );

        return true;
    }

    public function uploadSelectedElementImage(): void
    {
        $index = $this->selectedElementIndex();

        if ($index === null) {
            $this->addError(
                'elementImageUpload',
                'Select an image, logo, or sponsor logo element first.'
            );

            return;
        }

        $type =
            $this->elements[$index]['type']
                ?? null;

        if (! in_array(
            $type,
            ['image', 'logo', 'sponsor_logo'],
            true
        )) {
            $this->addError(
                'elementImageUpload',
                'The selected element does not support image uploads.'
            );

            return;
        }

        $this->validate([
            'elementImageUpload' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
                'dimensions:max_width=4000,max_height=4000',
            ],
        ]);

        $originalElement =
            $this->elements[$index];

        $oldPath =
            $originalElement['asset_path']
                ?? null;

        $newPath =
            $this->elementImageUpload->store(
                'ticket-template-elements',
                'public'
            );

        $this->elements[$index]['asset_path'] =
            $newPath;

        $this->isDirty = true;

        if (! $this->save()) {
            $this->elements[$index] =
                $originalElement;

            Storage::disk('public')->delete(
                $newPath
            );

            return;
        }

        if (
            filled($oldPath)
            && $oldPath !== $newPath
        ) {
            Storage::disk('public')->delete(
                $oldPath
            );
        }

        $this->elementImageUpload = null;

        $this->resetErrorBag(
            'elementImageUpload'
        );
    }

    public function removeSelectedElementImage(): void
    {
        $index = $this->selectedElementIndex();

        if ($index === null) {
            return;
        }

        $type =
            $this->elements[$index]['type']
                ?? null;

        if (! in_array(
            $type,
            ['image', 'logo', 'sponsor_logo'],
            true
        )) {
            return;
        }

        $oldPath =
            $this->elements[$index]['asset_path']
                ?? null;

        if (! filled($oldPath)) {
            return;
        }

        $originalElement =
            $this->elements[$index];

        unset(
            $this->elements[$index]['asset_path'],
            $this->elements[$index]['asset_id']
        );

        $this->isDirty = true;

        if (! $this->save()) {
            $this->elements[$index] =
                $originalElement;

            return;
        }

        Storage::disk('public')->delete(
            $oldPath
        );

        $this->elementImageUpload = null;

        $this->resetErrorBag(
            'elementImageUpload'
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
        $this->elementImageUpload = null;

        $this->resetErrorBag(
            'elementImageUpload'
        );

        if ($elementId === null) {
            $this->selectedElementId = null;

            $this->manualWidth = null;

            $this->manualHeight = null;

            return;
        }

        $element = collect(
            $this->elements
        )->first(
            fn (array $element): bool =>
                ($element['id'] ?? null)
                    === $elementId
        );

        $this->selectedElementId =
            $element !== null
                ? $elementId
                : null;

        $this->manualWidth =
            $element['width'] ?? null;

        $this->manualHeight =
            $element['height'] ?? null;
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

        $width = max(
            1,
            (float) ($this->elements[$index]['width'] ?? 1)
        );

        $height = max(
            1,
            (float) ($this->elements[$index]['height'] ?? 1)
        );

        $this->elements[$index]['x'] = max(
            0,
            min($x, max(0, $this->canvasWidth - $width))
        );

        $this->elements[$index]['y'] = max(
            0,
            min($y, max(0, $this->canvasHeight - $height))
        );

        $this->isDirty = true;
    }

    public function moveElement(
        string $elementId,
        int|float $x,
        int|float $y
    ): void {
        $this->selectElement(
            $elementId
        );

        if ($this->selectedElementId !== $elementId) {
            return;
        }

        $this->moveSelectedElement(
            $x,
            $y
        );
    }

    public function nudgeSelectedElement(
        int|float $deltaX,
        int|float $deltaY
    ): void {
        $index = $this->selectedElementIndex();

        if ($index === null) {
            return;
        }

        $x =
            (float) ($this->elements[$index]['x'] ?? 0)
            + $deltaX;

        $y =
            (float) ($this->elements[$index]['y'] ?? 0)
            + $deltaY;

        $this->moveSelectedElement(
            $x,
            $y
        );
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

        $x = max(
            0,
            (float) ($this->elements[$index]['x'] ?? 0)
        );

        $y = max(
            0,
            (float) ($this->elements[$index]['y'] ?? 0)
        );

        $this->elements[$index]['width'] = min(
            $width,
            max(1, $this->canvasWidth - $x)
        );

        $this->elements[$index]['height'] = min(
            $height,
            max(1, $this->canvasHeight - $y)
        );

        $this->manualWidth =
            $this->elements[$index]['width'];

        $this->manualHeight =
            $this->elements[$index]['height'];

        $this->isDirty = true;
    }

    public function resizeElement(
        string $elementId,
        int|float $width,
        int|float $height
    ): void {
        $this->selectElement(
            $elementId
        );

        if ($this->selectedElementId !== $elementId) {
            return;
        }

        $this->resizeSelectedElement(
            $width,
            $height
        );
    }

    public function applyManualSize(): void
    {
        $index = $this->selectedElementIndex();

        if ($index === null) {
            $this->addError(
                'manualWidth',
                'Select an element before applying its size.'
            );

            return;
        }

        $this->validate([
            'manualWidth' => [
                'required',
                'numeric',
                'min:1',
            ],
            'manualHeight' => [
                'required',
                'numeric',
                'min:1',
            ],
        ]);

        $width = (float) $this->manualWidth;

        $height = (float) $this->manualHeight;

        if (
            ($this->elements[$index]['type'] ?? null)
                === 'qr'
        ) {
            $height = $width;

            $this->manualHeight = $width;
        }

        $this->resizeSelectedElement(
            $width,
            $height
        );

        $this->dispatch(
            'ticket-element-sized',
            elementId: $this->selectedElementId,
            width: $this->manualWidth,
            height: $this->manualHeight
        );

        $this->resetErrorBag(
            'manualWidth'
        );

        $this->resetErrorBag(
            'manualHeight'
        );
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

        $this->manualWidth = null;

        $this->manualHeight = null;

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

    public function elementImageUploadName(): ?string
    {
        if ($this->elementImageUpload === null) {
            return null;
        }

        return $this->elementImageUpload
            ->getClientOriginalName();
    }

    public function elementImageUploadPreviewUrl(): ?string
    {
        if ($this->elementImageUpload === null) {
            return null;
        }

        try {
            $mimeType =
                $this->elementImageUpload
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

            return $this->elementImageUpload
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

        $offset =
            (count($this->elements) % 6)
                * 40;

        $element['x'] = min(
            max(0, $this->canvasWidth - (int) ($element['width'] ?? 100)),
            (int) ($element['x'] ?? 0) + $offset
        );

        $element['y'] = min(
            max(0, $this->canvasHeight - (int) ($element['height'] ?? 100)),
            (int) ($element['y'] ?? 0) + $offset
        );

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

        $this->elementImageUpload =
            null;

        $this->resetErrorBag(
            'backgroundUpload'
        );

        $this->resetErrorBag(
            'elementImageUpload'
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

        $this->manualWidth =
            null;

        $this->manualHeight =
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
