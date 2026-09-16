<?php

namespace Tests\Feature\Tickets;

use App\Filament\Resources\EventTicketTemplates\EventTicketTemplateResource;
use App\Filament\Resources\OrganizationTicketTemplates\OrganizationTicketTemplateResource;
use App\Models\Event;
use App\Models\EventTicketTemplate;
use App\Models\Organization;
use App\Models\OrganizationTicketTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketDesignerWrapperIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_designer_page_mounts_shared_ticket_designer_component(): void
    {
        $organization =
            Organization::query()->create([
                'name' => 'Event Organization',
            ]);

        $admin =
            User::query()->create([
                'name' => 'Super Admin',
                'email' => 'wrapper-event-admin@example.com',
                'password' => 'password',
                'is_super_admin' => true,
            ]);

        $event =
            Event::query()->create([
                'organization_id' =>
                    $organization->id,
                'name' =>
                    'Wrapper Event',
                'venue' =>
                    'Main Hall',
                'starts_at' =>
                    now()->addDay(),
                'status' =>
                    Event::STATUS_ACTIVE,
                'registration_is_open' =>
                    true,
            ]);

        $template =
            EventTicketTemplate::query()->create([
                'event_id' =>
                    $event->id,
                'ticket_type_id' =>
                    null,
                'source_organization_template_id' =>
                    null,
                'name' =>
                    'Event Designer Template',
                'width' =>
                    1080,
                'height' =>
                    1350,
                'is_active' =>
                    true,
                'is_default' =>
                    true,
            ]);

        $this->actingAs($admin);

        $this
            ->get(
                EventTicketTemplateResource::getUrl(
                    'designer',
                    [
                        'record' => $template,
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                'data-testid="event-ticket-template-designer"',
                false
            )
            ->assertSee(
                'data-testid="ticket-designer"',
                false
            )
            ->assertSee(
                'data-testid="ticket-designer-canvas"',
                false
            );
    }

    public function test_organization_designer_page_mounts_shared_ticket_designer_component(): void
    {
        $organization =
            Organization::query()->create([
                'name' => 'Template Organization',
            ]);

        $admin =
            User::query()->create([
                'name' => 'Super Admin',
                'email' => 'wrapper-org-admin@example.com',
                'password' => 'password',
                'is_super_admin' => true,
            ]);

        $template =
            OrganizationTicketTemplate::query()->create([
                'organization_id' =>
                    $organization->id,
                'name' =>
                    'Organization Designer Template',
                'width' =>
                    1080,
                'height' =>
                    1350,
                'is_active' =>
                    true,
                'is_default' =>
                    true,
            ]);

        $this->actingAs($admin);

        $this
            ->get(
                OrganizationTicketTemplateResource::getUrl(
                    'designer',
                    [
                        'record' => $template,
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                'data-testid="organization-ticket-template-designer"',
                false
            )
            ->assertSee(
                'data-testid="ticket-designer"',
                false
            )
            ->assertSee(
                'data-testid="ticket-designer-canvas"',
                false
            );
    }
}
