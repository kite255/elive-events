<?php

namespace App\Services\Tickets;

use App\Models\EventTicketTemplate;
use App\Models\OrganizationTicketTemplate;
use App\Models\TicketTemplatePage;
use App\Models\TicketTemplateAsset;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
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
        $template = match ($type) {
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

        $this->authorizeTemplate($template);

        return $template;
    }

    private function authorizeTemplate(OrganizationTicketTemplate|EventTicketTemplate $template): void
    {
        // Preserve trusted console and background service callers without an HTTP actor.
        // Every authenticated caller must pass the current policy using a fresh user.
        if (auth()->check()) {
            $actor = User::query()->find(auth()->id());
            abort_unless($actor, 403);
            Gate::forUser($actor)->authorize('update', $template);
        } elseif (! app()->runningInConsole() || app()->bound('request') && request()->route() !== null) {
            abort(403);
        }
    }

    private function authorizedPage(TicketTemplatePage $page): TicketTemplatePage
    {
        $page = TicketTemplatePage::query()->findOrFail($page->id);
        $eventId = $page->event_ticket_template_id;
        $organizationId = $page->organization_ticket_template_id;
        if (($eventId === null) === ($organizationId === null)) {
            throw new InvalidArgumentException('Ticket template page must have exactly one parent template.');
        }
        $this->loadTemplate($eventId !== null ? self::TYPE_EVENT : self::TYPE_ORGANIZATION, (int) ($eventId ?? $organizationId));

        return $page;
    }

    public function pages(
        string $type,
        int $templateId
    ): Collection {
        $template = $this->loadTemplate($type, $templateId);

        return $template->pages()
            ->orderBy('page_number')
            ->get();
    }

    public function ensureInitialPage(
        string $type,
        int $templateId
    ): TicketTemplatePage {
        $template = $this->loadTemplate($type, $templateId);

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
        $template = $this->loadTemplate($type, $templateId);

        $nextPageNumber = (
            (int) $template->pages()->max('page_number')
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
        $page = $this->authorizedPage($page);
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

    public function resizeTemplate(
        string $type,
        int $templateId,
        int $oldWidth,
        int $oldHeight,
        int $newWidth,
        int $newHeight
    ): void {
        $template = $this->loadTemplate($type, $templateId);
        $this->validator->validate(['version' => 1, 'elements' => []], $newWidth, $newHeight);

        DB::transaction(function () use ($template, $oldWidth, $oldHeight, $newWidth, $newHeight): void {
            $template = $template->newQuery()->lockForUpdate()->findOrFail($template->id);
            $this->authorizeTemplate($template);
            if ((int) $template->width !== $oldWidth || (int) $template->height !== $oldHeight) {
                throw ValidationException::withMessages(['width' => ['Canvas dimensions have changed. Reload the template before resizing.']]);
            }
            if ($oldWidth <= 0 || $oldHeight <= 0) {
                throw ValidationException::withMessages(['width' => ['Existing canvas dimensions are invalid.']]);
            }
            if ($oldWidth === $newWidth && $oldHeight === $newHeight) {
                return;
            }

            $scaleX = $newWidth / $oldWidth;
            $scaleY = $newHeight / $oldHeight;
            $fontScale = min($scaleX, $scaleY);

            foreach ($template->pages()->lockForUpdate()->get() as $page) {
                $definition = $page->definition ?? [];
                $elements = $definition['elements'] ?? [];

                foreach ($elements as &$element) {
                    $element['x'] = (
                        (float) ($element['x'] ?? 0)
                    ) * $scaleX;

                    $element['y'] = (
                        (float) ($element['y'] ?? 0)
                    ) * $scaleY;

                    $element['width'] = (
                        (float) ($element['width'] ?? 0)
                    ) * $scaleX;

                    $element['height'] = (
                        (float) ($element['height'] ?? 0)
                    ) * $scaleY;

                    if (
                        ($element['type'] ?? null) === 'text'
                        && isset(
                            $element['style']['fontSize']
                        )
                    ) {
                        $element['style']['fontSize'] = round(
                            (float) $element['style']['fontSize']
                                * $fontScale,
                            2
                        );
                    }

                    $isQr = in_array(
                        $element['type'] ?? null,
                        ['qr', 'qr_code'],
                        true
                    ) || (
                        $element['binding'] ?? null
                    ) === 'ticket_qr';

                    if ($isQr) {
                        $size = min(
                            (float) $element['width'],
                            (float) $element['height'],
                            $newWidth,
                            $newHeight
                        );

                        $element['width'] = $size;
                        $element['height'] = $size;
                    }

                    $element['width'] = min(
                        (float) $element['width'],
                        $newWidth
                    );

                    $element['height'] = min(
                        (float) $element['height'],
                        $newHeight
                    );

                    $element['x'] = max(
                        0,
                        min(
                            (float) $element['x'],
                            max(
                                0,
                                $newWidth
                                    - (float) $element['width']
                            )
                        )
                    );

                    $element['y'] = max(
                        0,
                        min(
                            (float) $element['y'],
                            max(
                                0,
                                $newHeight
                                    - (float) $element['height']
                            )
                        )
                    );
                }

                unset($element);

                $definition['elements'] = $elements;

                $this->validator->validate($definition, $newWidth, $newHeight);
                $this->validateAssetReferences($page, $definition);
                $page->update([
                    'definition' => $definition,
                ]);
            }

            $template->update([
                'width' => $newWidth,
                'height' => $newHeight,
            ]);
        });
    }

    public function saveDefinition(
        TicketTemplatePage $page,
        array $definition
    ): TicketTemplatePage {
        $page = $this->authorizedPage($page);
        $eventTemplateId = $page->event_ticket_template_id;

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

        $validatedDefinition = $this->validator->validate(
            $definition,
            (int) $template->width,
            (int) $template->height
        );

        $this->validateAssetReferences($page, $validatedDefinition);

        $page->update([
            'definition' => $validatedDefinition,
        ]);

        return $page->refresh();
    }

    private function validateAssetReferences(TicketTemplatePage $page, array $definition): void
    {
        $eventId = $page->event_ticket_template_id;
        $organizationId = $page->organization_ticket_template_id;
        $prefix = 'ticket-template-elements/'.($eventId !== null ? 'event/'.$eventId : 'organization/'.$organizationId).'/';
        $existing = collect($page->definition['elements'] ?? [])->keyBy('id');

        foreach ($definition['elements'] as $index => $element) {
            $path = $element['asset_path'] ?? null;
            $assetId = $element['asset_id'] ?? null;
            if ($path === null && $assetId === null) {
                continue;
            }

            $reject = static function () use ($index): never {
                throw ValidationException::withMessages([
                    "elements.{$index}.asset_path" => ['The image asset does not belong to this ticket template.'],
                ]);
            };

            if (! in_array($element['type'], ['image', 'logo', 'sponsor_logo'], true)) {
                $reject();
            }

            if ($assetId !== null) {
                if (! is_int($assetId) && (! is_string($assetId) || ! ctype_digit($assetId))) {
                    $reject();
                }
                $asset = TicketTemplateAsset::query()
                    ->where('event_ticket_template_id', $eventId)
                    ->where('organization_ticket_template_id', $organizationId)
                    ->whereIn('asset_type', ['image', 'logo', 'sponsor_logo'])
                    ->find($assetId);
                if (! $asset || ($path !== null && $path !== $asset->file_path)) {
                    $reject();
                }
                $path = $asset->file_path;
            }

            if (! is_string($path) || ! preg_match('~\A ticket-template-elements/(?:[a-z]+/[0-9]+/)?[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)\z~ix', $path)) {
                $reject();
            }

            $previous = $existing->get($element['id'], []);
            $legacy = preg_match('~\Aticket-template-elements/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)\z~i', $path)
                && ($previous['asset_path'] ?? null) === $path;
            if (! $legacy && (! str_starts_with($path, $prefix) || ! Storage::disk('public')->exists($path))) {
                $reject();
            }
        }
    }

}