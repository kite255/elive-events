<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\EventTicketTemplate;
use App\Models\Organization;
use App\Models\OrganizationTicketTemplate;
use App\Services\Tickets\TicketDesignerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TicketDesignerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_loads_an_organization_ticket_template(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $template = OrganizationTicketTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Corporate Template',
            'width' => 1080,
            'height' => 1350,
            'is_active' => true,
            'is_default' => false,
        ]);

        $loaded = $this->service()->loadTemplate(
            TicketDesignerService::TYPE_ORGANIZATION,
            $template->id
        );

        $this->assertTrue($loaded->is($template));
    }

    public function test_it_loads_an_event_ticket_template(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Event A',
        ]);

        $template = EventTicketTemplate::query()->create([
            'event_id' => $event->id,
            'name' => 'Event Template',
            'width' => 1080,
            'height' => 1350,
            'is_active' => true,
            'is_default' => false,
        ]);

        $loaded = $this->service()->loadTemplate(
            TicketDesignerService::TYPE_EVENT,
            $template->id
        );

        $this->assertTrue($loaded->is($template));
    }

    public function test_it_rejects_an_unknown_template_type(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->service()->loadTemplate(
            'unknown',
            1
        );
    }

    public function test_it_creates_an_initial_page_for_an_organization_template(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $template = OrganizationTicketTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Corporate Template',
            'width' => 1080,
            'height' => 1350,
            'is_active' => true,
            'is_default' => false,
        ]);

        $page = $this->service()->ensureInitialPage(
            TicketDesignerService::TYPE_ORGANIZATION,
            $template->id
        );

        $this->assertSame(1, $page->page_number);
        $this->assertSame('Page 1', $page->name);

        $this->assertSame(
            $template->id,
            $page->organization_ticket_template_id
        );

        $this->assertNull(
            $page->event_ticket_template_id
        );

        $this->assertSame(
            [
                'version' => 1,
                'elements' => [],
            ],
            $page->definition
        );
    }

    public function test_ensure_initial_page_does_not_create_duplicates(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Event A',
        ]);

        $template = EventTicketTemplate::query()->create([
            'event_id' => $event->id,
            'name' => 'Event Template',
            'width' => 1080,
            'height' => 1350,
            'is_active' => true,
            'is_default' => false,
        ]);

        $first = $this->service()->ensureInitialPage(
            TicketDesignerService::TYPE_EVENT,
            $template->id
        );

        $second = $this->service()->ensureInitialPage(
            TicketDesignerService::TYPE_EVENT,
            $template->id
        );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertCount(
            1,
            $template->fresh()->pages
        );
    }

    public function test_create_page_increments_page_number_and_attaches_correct_parent(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Event A',
        ]);

        $template = EventTicketTemplate::query()->create([
            'event_id' => $event->id,
            'name' => 'Event Template',
            'width' => 1080,
            'height' => 1350,
            'is_active' => true,
            'is_default' => false,
        ]);

        $service = $this->service();

        $service->ensureInitialPage(
            TicketDesignerService::TYPE_EVENT,
            $template->id
        );

        $page = $service->createPage(
            TicketDesignerService::TYPE_EVENT,
            $template->id
        );

        $this->assertSame(2, $page->page_number);
        $this->assertSame('Page 2', $page->name);

        $this->assertSame(
            $template->id,
            $page->event_ticket_template_id
        );

        $this->assertNull(
            $page->organization_ticket_template_id
        );
    }

    public function test_it_saves_a_valid_definition(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $template = OrganizationTicketTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Corporate Template',
            'width' => 1080,
            'height' => 1350,
            'is_active' => true,
            'is_default' => false,
        ]);

        $service = $this->service();

        $page = $service->ensureInitialPage(
            TicketDesignerService::TYPE_ORGANIZATION,
            $template->id
        );

        $definition = [
            'version' => 1,
            'elements' => [
                [
                    'id' => 'name_001',
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
                ],
            ],
        ];

        $saved = $service->saveDefinition(
            $page,
            $definition
        );

        $this->assertSame(
            $definition,
            $saved->fresh()->definition
        );
    }

    public function test_invalid_definition_does_not_replace_existing_definition(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $template = OrganizationTicketTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Corporate Template',
            'width' => 1080,
            'height' => 1350,
            'is_active' => true,
            'is_default' => false,
        ]);

        $service = $this->service();

        $page = $service->ensureInitialPage(
            TicketDesignerService::TYPE_ORGANIZATION,
            $template->id
        );

        $original = $page->definition;

        try {
            $service->saveDefinition(
                $page,
                [
                    'version' => 1,
                    'elements' => [
                        [
                            'id' => 'bad_001',
                            'type' => 'script',
                            'x' => 0,
                            'y' => 0,
                            'width' => 100,
                            'height' => 100,
                        ],
                    ],
                ]
            );
        } catch (\Throwable) {
            // Expected: existing validator rejects invalid definition.
        }

        $this->assertSame(
            $original,
            $page->fresh()->definition
        );
    }

    private function service(): TicketDesignerService
    {
        return app(TicketDesignerService::class);
    }
}
