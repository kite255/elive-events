<?php

namespace App\Livewire\Tickets;

use App\Models\TicketTemplatePage;
use App\Services\Tickets\TicketDesignerService;
use Livewire\Component;

class TicketDesigner extends Component
{
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

    protected function reloadPages(): void
    {
        $service = app(
            TicketDesignerService::class
        );

        $this->pages = $service->pages(
            $this->templateType,
            $this->templateId
        )
            ->map(
                fn (
                    TicketTemplatePage $page
                ): array => [
                    'id' => $page->id,
                    'page_number' =>
                        $page->page_number,
                    'name' => $page->name,
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
            fn (array $page): bool =>
                (int) $page['id']
                    === $pageId
        );

        if ($page === null) {
            return;
        }

        $this->activePageId =
            (int) $page['id'];

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

        $this->isDirty = false;
    }

    protected function pageBelongsToTemplate(
        int $pageId
    ): bool {
        return collect(
            $this->pages
        )->contains(
            fn (array $page): bool =>
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