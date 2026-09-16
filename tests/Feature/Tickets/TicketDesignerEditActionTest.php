<?php

namespace Tests\Feature\Tickets;

use App\Filament\Resources\EventTicketTemplates\EventTicketTemplateResource;
use App\Filament\Resources\EventTicketTemplates\Pages\EditEventTicketTemplate;
use App\Filament\Resources\OrganizationTicketTemplates\OrganizationTicketTemplateResource;
use App\Filament\Resources\OrganizationTicketTemplates\Pages\EditOrganizationTicketTemplate;
use App\Models\Event;
use App\Models\EventTicketTemplate;
use App\Models\Organization;
use App\Models\OrganizationTicketTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketDesignerEditActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_template_edit_page_has_design_ticket_action(): void
    {
        [$admin, $template] =
            $this->makeEventTemplate();

        $this->actingAs($admin);

        Livewire::test(
            EditEventTicketTemplate::class,
            [
                'record' => $template->getRouteKey(),
            ]
        )
            ->assertActionVisible(
                'design'
            )
            ->assertSee(
                'Design Ticket'
            );
    }

    public function test_event_template_design_action_points_to_designer_route(): void
    {
        [$admin, $template] =
            $this->makeEventTemplate();

        $this->actingAs($admin);

        $expectedUrl =
            EventTicketTemplateResource::getUrl(
                'designer',
                [
                    'record' => $template,
                ]
            );

        Livewire::test(
            EditEventTicketTemplate::class,
            [
                'record' => $template->getRouteKey(),
            ]
        )
            ->assertActionVisible(
                'design'
            )
            ->assertSeeHtml(
                e($expectedUrl)
            );
    }

    public function test_organization_template_edit_page_has_design_ticket_action(): void
    {
        [$admin, $template] =
            $this->makeOrganizationTemplate();

        $this->actingAs($admin);

        Livewire::test(
            EditOrganizationTicketTemplate::class,
            [
                'record' => $template->getRouteKey(),
            ]
        )
            ->assertActionVisible(
                'design'
            )
            ->assertSee(
                'Design Ticket'
            );
    }

    public function test_organization_template_design_action_points_to_designer_route(): void
    {
        [$admin, $template] =
            $this->makeOrganizationTemplate();

        $this->actingAs($admin);

        $expectedUrl =
            OrganizationTicketTemplateResource::getUrl(
                'designer',
                [
                    'record' => $template,
                ]
            );

        Livewire::test(
            EditOrganizationTicketTemplate::class,
            [
                'record' => $template->getRouteKey(),
            ]
        )
            ->assertActionVisible(
                'design'
            )
            ->assertSeeHtml(
                e($expectedUrl)
            );
    }

    private function makeEventTemplate(): array
    {
        $organization =
            Organization::query()->create([
                'name' => 'Event Organization',
            ]);

        $admin =
            User::query()->create([
                'name' => 'Super Admin',
                'email' => 'superadmin-event-template@example.com',
                'password' => 'password',
                'is_super_admin' => true,
            ]);

        $event =
            Event::query()->create([
                'organization_id' =>
                    $organization->id,
                'name' =>
                    'Designer Event',
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
                    'Event Ticket Template',
                'width' =>
                    1080,
                'height' =>
                    1350,
                'is_active' =>
                    true,
                'is_default' =>
                    true,
            ]);

        return [
            $admin,
            $template,
        ];
    }

    private function makeOrganizationTemplate(): array
    {
        $organization =
            Organization::query()->create([
                'name' =>
                    'Template Organization',
            ]);

        $admin =
            User::query()->create([
                'name' =>
                    'Super Admin',
                'email' =>
                    'superadmin-org-template@example.com',
                'password' =>
                    'password',
                'is_super_admin' =>
                    true,
            ]);

        $template =
            OrganizationTicketTemplate::query()->create([
                'organization_id' =>
                    $organization->id,
                'name' =>
                    'Organization Ticket Template',
                'width' =>
                    1080,
                'height' =>
                    1350,
                'is_active' =>
                    true,
                'is_default' =>
                    true,
            ]);

        return [
            $admin,
            $template,
        ];
    }
}