<?php

namespace Tests\Feature\Tickets;

use App\Livewire\Tickets\TicketDesigner;
use App\Models\Event;
use App\Models\EventTicketTemplate;
use App\Models\Organization;
use App\Models\OrganizationTicketTemplate;
use App\Models\TicketTemplatePage;
use App\Services\Tickets\TicketDesignerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketDesignerLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_loads_organization_template_metadata(): void
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

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' =>
                    TicketDesignerService::TYPE_ORGANIZATION,

                'templateId' =>
                    $template->id,
            ]
        )
            ->assertSet(
                'templateType',
                TicketDesignerService::TYPE_ORGANIZATION
            )
            ->assertSet(
                'templateId',
                $template->id
            )
            ->assertSet(
                'templateName',
                'Corporate Template'
            )
            ->assertSet(
                'canvasWidth',
                1080
            )
            ->assertSet(
                'canvasHeight',
                1350
            );
    }

    public function test_component_creates_initial_page_when_template_has_none(): void
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

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' =>
                    TicketDesignerService::TYPE_ORGANIZATION,

                'templateId' =>
                    $template->id,
            ]
        )
            ->assertSet(
                'activePageId',
                fn ($value): bool =>
                    is_int($value)
                    && $value > 0
            )
            ->assertSet(
                'elements',
                []
            );

        $this->assertCount(
            1,
            $template->fresh()->pages
        );

        $page = $template->fresh()
            ->pages()
            ->first();

        $this->assertSame(
            'Page 1',
            $page->name
        );

        $this->assertSame(
            [
                'version' => 1,
                'elements' => [],
            ],
            $page->definition
        );
    }

    public function test_component_loads_existing_page_definition(): void
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

        $page = TicketTemplatePage::query()->create([
            'event_ticket_template_id' =>
                $template->id,

            'page_number' => 1,

            'name' => 'Front',

            'definition' => [
                'version' => 1,
                'elements' => [
                    [
                        'id' => 'name_001',
                        'type' => 'text',
                        'binding' => 'holder_name',
                        'x' => 100,
                        'y' => 120,
                        'width' => 500,
                        'height' => 100,
                        'rotation' => 0,
                    ],
                ],
            ],
        ]);

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' =>
                    TicketDesignerService::TYPE_EVENT,

                'templateId' =>
                    $template->id,
            ]
        )
            ->assertSet(
                'activePageId',
                $page->id
            )
            ->assertSet(
                'elements.0.id',
                'name_001'
            )
            ->assertSet(
                'elements.0.type',
                'text'
            );
    }

    public function test_switch_page_loads_only_selected_page_elements(): void
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

        $firstPage = TicketTemplatePage::query()->create([
            'organization_ticket_template_id' =>
                $template->id,

            'page_number' => 1,

            'name' => 'Front',

            'definition' => [
                'version' => 1,
                'elements' => [
                    [
                        'id' => 'front_001',
                        'type' => 'text',
                        'binding' => 'event_name',
                        'x' => 100,
                        'y' => 100,
                        'width' => 400,
                        'height' => 80,
                        'rotation' => 0,
                    ],
                ],
            ],
        ]);

        $secondPage = TicketTemplatePage::query()->create([
            'organization_ticket_template_id' =>
                $template->id,

            'page_number' => 2,

            'name' => 'Back',

            'definition' => [
                'version' => 1,
                'elements' => [
                    [
                        'id' => 'back_001',
                        'type' => 'qr',
                        'binding' => 'ticket_qr',
                        'x' => 300,
                        'y' => 300,
                        'width' => 250,
                        'height' => 250,
                        'rotation' => 0,
                    ],
                ],
            ],
        ]);

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' =>
                    TicketDesignerService::TYPE_ORGANIZATION,

                'templateId' =>
                    $template->id,
            ]
        )
            ->assertSet(
                'activePageId',
                $firstPage->id
            )
            ->call(
                'switchPage',
                $secondPage->id
            )
            ->assertSet(
                'activePageId',
                $secondPage->id
            )
            ->assertSet(
                'elements.0.id',
                'back_001'
            )
            ->assertSet(
                'selectedElementId',
                null
            );
    }

    public function test_cannot_switch_to_page_from_another_template(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $firstTemplate =
            OrganizationTicketTemplate::query()->create([
                'organization_id' => $organization->id,
                'name' => 'Template A',
                'width' => 1080,
                'height' => 1350,
                'is_active' => true,
                'is_default' => false,
            ]);

        $secondTemplate =
            OrganizationTicketTemplate::query()->create([
                'organization_id' => $organization->id,
                'name' => 'Template B',
                'width' => 1080,
                'height' => 1350,
                'is_active' => true,
                'is_default' => false,
            ]);

        $foreignPage =
            TicketTemplatePage::query()->create([
                'organization_ticket_template_id' =>
                    $secondTemplate->id,

                'page_number' => 1,
                'name' => 'Foreign',

                'definition' => [
                    'version' => 1,
                    'elements' => [],
                ],
            ]);

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' =>
                    TicketDesignerService::TYPE_ORGANIZATION,

                'templateId' =>
                    $firstTemplate->id,
            ]
        )
            ->call(
                'switchPage',
                $foreignPage->id
            )
            ->assertHasErrors();
    }

    public function test_selection_only_accepts_element_on_active_page(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $template = OrganizationTicketTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Template A',
            'width' => 1080,
            'height' => 1350,
            'is_active' => true,
            'is_default' => false,
        ]);

        TicketTemplatePage::query()->create([
            'organization_ticket_template_id' =>
                $template->id,

            'page_number' => 1,
            'name' => 'Front',

            'definition' => [
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
                    ],
                ],
            ],
        ]);

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' =>
                    TicketDesignerService::TYPE_ORGANIZATION,

                'templateId' =>
                    $template->id,
            ]
        )
            ->call(
                'selectElement',
                'name_001'
            )
            ->assertSet(
                'selectedElementId',
                'name_001'
            )
            ->call(
                'selectElement',
                'does_not_exist'
            )
            ->assertSet(
                'selectedElementId',
                null
            );
    }

public function test_add_text_creates_and_selects_text_element(): void
{
    [$templateType, $templateId] =
        $this->makeOrganizationTemplateContext();

    Livewire::test(
        TicketDesigner::class,
        [
            'templateType' => $templateType,
            'templateId' => $templateId,
        ]
    )
        ->call('addText')
        ->assertSet(
            'elements.0.type',
            'text'
        )
        ->assertSet(
            'elements.0.binding',
            'holder_name'
        )
        ->assertSet(
            'selectedElementId',
            fn ($value): bool =>
                is_string($value)
                && $value !== ''
        )
        ->assertSet(
            'isDirty',
            true
        );
}

public function test_add_qr_creates_fixed_ticket_qr_binding(): void
{
    [$templateType, $templateId] =
        $this->makeOrganizationTemplateContext();

    Livewire::test(
        TicketDesigner::class,
        [
            'templateType' => $templateType,
            'templateId' => $templateId,
        ]
    )
        ->call('addQr')
        ->assertSet(
            'elements.0.type',
            'qr'
        )
        ->assertSet(
            'elements.0.binding',
            'ticket_qr'
        )
        ->assertSet(
            'isDirty',
            true
        );
}

public function test_supported_element_methods_create_expected_types(): void
{
    [$templateType, $templateId] =
        $this->makeOrganizationTemplateContext();

    Livewire::test(
        TicketDesigner::class,
        [
            'templateType' => $templateType,
            'templateId' => $templateId,
        ]
    )
        ->call('addImage')
        ->call('addLogo')
        ->call('addSponsorLogo')
        ->call('addShape')
        ->call('addLine')
        ->assertSet(
            'elements.0.type',
            'image'
        )
        ->assertSet(
            'elements.1.type',
            'logo'
        )
        ->assertSet(
            'elements.2.type',
            'sponsor_logo'
        )
        ->assertSet(
            'elements.3.type',
            'shape'
        )
        ->assertSet(
            'elements.4.type',
            'line'
        );
}

public function test_new_elements_receive_unique_ids(): void
{
    [$templateType, $templateId] =
        $this->makeOrganizationTemplateContext();

    $component = Livewire::test(
        TicketDesigner::class,
        [
            'templateType' => $templateType,
            'templateId' => $templateId,
        ]
    )
        ->call('addText')
        ->call('addText');

    $elements = $component->get(
        'elements'
    );

    $this->assertCount(
        2,
        $elements
    );

    $this->assertNotSame(
        $elements[0]['id'],
        $elements[1]['id']
    );
}

public function test_delete_selected_element_removes_only_selected_element(): void
{
    [$templateType, $templateId] =
        $this->makeOrganizationTemplateContext();

    $component = Livewire::test(
        TicketDesigner::class,
        [
            'templateType' => $templateType,
            'templateId' => $templateId,
        ]
    )
        ->call('addText')
        ->call('addQr');

    $elements = $component->get(
        'elements'
    );

    $textId = $elements[0]['id'];
    $qrId = $elements[1]['id'];

    $component
        ->call(
            'selectElement',
            $textId
        )
        ->call(
            'deleteSelectedElement'
        )
        ->assertSet(
            'selectedElementId',
            null
        )
        ->assertSet(
            'isDirty',
            true
        );

    $remaining = $component->get(
        'elements'
    );

    $this->assertCount(
        1,
        $remaining
    );

    $this->assertSame(
        $qrId,
        $remaining[0]['id']
    );
}

public function test_delete_with_unknown_selection_does_not_remove_elements(): void
{
    [$templateType, $templateId] =
        $this->makeOrganizationTemplateContext();

    $component = Livewire::test(
        TicketDesigner::class,
        [
            'templateType' => $templateType,
            'templateId' => $templateId,
        ]
    )
        ->call('addText');

    $elementsBefore =
        $component->get('elements');

    $component
        ->set(
            'selectedElementId',
            'not_real'
        )
        ->call(
            'deleteSelectedElement'
        );

    $this->assertSame(
        $elementsBefore,
        $component->get('elements')
    );
}


    private function makeOrganizationTemplateContext(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $template = OrganizationTicketTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Designer Template',
            'width' => 1080,
            'height' => 1350,
            'is_active' => true,
            'is_default' => false,
        ]);

        return [
            TicketDesignerService::TYPE_ORGANIZATION,
            $template->id,
        ];
    }

}
