<?php

namespace App\Services\Tickets;

use App\Models\EventTicketTemplate;
use App\Models\OrganizationTicketTemplate;
use App\Models\TicketTemplatePage;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class TicketDesignerService
{
    public const TYPE_EVENT = 'event';

    public const TYPE_ORGANIZATION = 'organization';

    public function __construct(
        private readonly TicketTemplateDefinitionValidator $validator
    ) {
    }

    public function loadTemplate(
        string $type,
        int $templateId
    ): OrganizationTicketTemplate|EventTicketTemplate {
        return match ($type) {
            self::TYPE_ORGANIZATION =>
                OrganizationTicketTemplate::query()->findOrFail(
                    $templateId
                ),

            self::TYPE_EVENT =>
                EventTicketTemplate::query()->findOrFail(
                    $templateId
                ),

            default => throw new InvalidArgumentException(
                "Unsupported ticket template type [{$type}]."
            ),
        };
    }

    public function pages(
        string $type,
        int $templateId
    ): Collection {
        $template = $this->loadTemplate(
            $type,
            $templateId
        );

        return $template->pages()
            ->orderBy('page_number')
            ->get();
    }

    public function ensureInitialPage(
        string $type,
        int $templateId
    ): TicketTemplatePage {
        $template = $this->loadTemplate(
            $type,
            $templateId
        );

        $existingPage = $template->pages()
            ->orderBy('page_number')
            ->first();

        if ($existingPage) {
            return $existingPage;
        }

        return $template->pages()->create([
            'page_number' => 1,
            'name' => 'Page 1',
            'definition' => [
                'version' => 1,
                'elements' => [],
            ],
        ]);
    }

    public function createPage(
        string $type,
        int $templateId,
        ?string $name = null
    ): TicketTemplatePage {
        $template = $this->loadTemplate(
            $type,
            $templateId
        );

        $nextPageNumber = (
            (int) $template->pages()
                ->max('page_number')
        ) + 1;

        return $template->pages()->create([
            'page_number' => $nextPageNumber,
            'name' => filled($name)
                ? trim($name)
                : "Page {$nextPageNumber}",
            'definition' => [
                'version' => 1,
                'elements' => [],
            ],
        ]);
    }

    public function renamePage(
        TicketTemplatePage $page,
        string $name
    ): TicketTemplatePage {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException(
                'Page name cannot be empty.'
            );
        }

        $page->update([
            'name' => $name,
        ]);

        return $page->refresh();
    }

    public function saveDefinition(
        TicketTemplatePage $page,
        array $definition
    ): TicketTemplatePage {
        $eventTemplateId =
            $page->event_ticket_template_id;

        $organizationTemplateId =
            $page->organization_ticket_template_id;

        if (
            $eventTemplateId !== null
            && $organizationTemplateId !== null
        ) {
            throw new InvalidArgumentException(
                'Ticket template page cannot belong to both an event template and an organization template.'
            );
        }

        if ($eventTemplateId !== null) {
            $template = $page->eventTemplate()
                ->firstOrFail();
        } elseif ($organizationTemplateId !== null) {
            $template = $page->organizationTemplate()
                ->firstOrFail();
        } else {
            throw new InvalidArgumentException(
                'Ticket template page has no parent template.'
            );
        }

        $validatedDefinition =
            $this->validator->validate(
                $definition,
                (int) $template->width,
                (int) $template->height
            );

        $page->update([
            'definition' => $validatedDefinition,
        ]);

        return $page->refresh();
    }
}