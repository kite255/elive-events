<?php

namespace Tests\Feature\Tickets;

use App\Filament\Resources\EventTicketTemplates\Pages\CreateEventTicketTemplate;
use App\Filament\Resources\OrganizationTicketTemplates\Pages\CreateOrganizationTicketTemplate;
use App\Models\Event;
use App\Models\EventTicketTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketTemplateFilamentFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_template_form_has_required_fields(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(
            CreateOrganizationTicketTemplate::class
        )
            ->assertFormFieldExists('organization_id')
            ->assertFormFieldExists('name')
            ->assertFormFieldExists('description')
            ->assertFormFieldExists('width')
            ->assertFormFieldExists('height')
            ->assertFormFieldExists('is_active')
            ->assertFormFieldExists('is_default');
    }

    public function test_event_template_form_has_required_fields_for_super_admin(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(
            CreateEventTicketTemplate::class
        )
            ->assertFormFieldExists('event_id')
            ->assertFormFieldExists('ticket_type_id')
            ->assertFormFieldExists(
                'source_organization_template_id'
            )
            ->assertFormFieldExists('name')
            ->assertFormFieldExists('width')
            ->assertFormFieldExists('height')
            ->assertFormFieldExists('is_active')
            ->assertFormFieldExists('is_default');
    }

    public function test_event_manager_can_open_event_template_create_form(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Event A',
        ]);

        $user = User::factory()->create();

        $organization->attachUser(
            $user,
            User::ORGANIZATION_ROLE_EVENT_MANAGER
        );

        $user->assignToEvent(
            $event,
            User::ORGANIZATION_ROLE_EVENT_MANAGER
        );

        $this->actingAs($user);

        Livewire::test(
            CreateEventTicketTemplate::class
        )
            ->assertOk()
            ->assertFormFieldExists('event_id')
            ->assertFormFieldExists('name');
    }

    public function test_event_manager_cannot_create_template_for_unassigned_event(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $assignedEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Assigned Event',
        ]);

        $unassignedEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Unassigned Event',
        ]);

        $user = User::factory()->create();

        $organization->attachUser(
            $user,
            User::ORGANIZATION_ROLE_EVENT_MANAGER
        );

        $user->assignToEvent(
            $assignedEvent,
            User::ORGANIZATION_ROLE_EVENT_MANAGER
        );

        $this->actingAs($user);

        Livewire::test(
            CreateEventTicketTemplate::class
        )
            ->fillForm([
                'event_id' => $unassignedEvent->id,
                'name' => 'Unauthorized Template',
                'width' => 1080,
                'height' => 1350,
                'is_active' => true,
                'is_default' => false,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'event_id',
            ]);

        $this->assertFalse(
            EventTicketTemplate::query()
                ->where(
                    'event_id',
                    $unassignedEvent->id
                )
                ->where(
                    'name',
                    'Unauthorized Template'
                )
                ->exists()
        );
    }


}
