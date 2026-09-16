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



    public function test_move_selected_element_updates_coordinates(): void
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

        $elementId =
            $component->get('elements')[0]['id'];

        $component
            ->call(
                'moveSelectedElement',
                250,
                375
            )
            ->assertSet(
                'elements.0.x',
                250
            )
            ->assertSet(
                'elements.0.y',
                375
            )
            ->assertSet(
                'selectedElementId',
                $elementId
            )
            ->assertSet(
                'isDirty',
                true
            );
    }

    public function test_resize_selected_element_updates_dimensions(): void
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
            ->call(
                'resizeSelectedElement',
                640,
                140
            )
            ->assertSet(
                'elements.0.width',
                640
            )
            ->assertSet(
                'elements.0.height',
                140
            );
    }

    public function test_invalid_resize_does_not_change_element(): void
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

        $before = $component->get(
            'elements'
        );

        $component->call(
            'resizeSelectedElement',
            -10,
            0
        );

        $this->assertSame(
            $before,
            $component->get('elements')
        );
    }

    public function test_update_selected_element_can_change_allowed_properties(): void
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
            ->call(
                'updateSelectedElement',
                [
                    'rotation' => 15,
                    'binding' => 'event_name',
                ]
            )
            ->assertSet(
                'elements.0.rotation',
                15
            )
            ->assertSet(
                'elements.0.binding',
                'event_name'
            );
    }

    public function test_update_selected_element_cannot_change_id_or_type(): void
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

        $original =
            $component->get('elements')[0];

        $component->call(
            'updateSelectedElement',
            [
                'id' => 'forged_id',
                'type' => 'qr',
            ]
        );

        $element =
            $component->get('elements')[0];

        $this->assertSame(
            $original['id'],
            $element['id']
        );

        $this->assertSame(
            'text',
            $element['type']
        );
    }

    public function test_qr_binding_cannot_be_changed(): void
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
            ->call(
                'updateSelectedElement',
                [
                    'binding' => 'holder_name',
                ]
            )
            ->assertSet(
                'elements.0.binding',
                'ticket_qr'
            );
    }

    public function test_move_layer_forward_changes_element_order(): void
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

        $elements =
            $component->get('elements');

        $textId = $elements[0]['id'];

        $component
            ->call(
                'selectElement',
                $textId
            )
            ->call(
                'moveLayerForward'
            );

        $after =
            $component->get('elements');

        $this->assertSame(
            $textId,
            $after[1]['id']
        );
    }

    public function test_move_layer_backward_changes_element_order(): void
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

        $elements =
            $component->get('elements');

        $qrId = $elements[1]['id'];

        $component
            ->call(
                'selectElement',
                $qrId
            )
            ->call(
                'moveLayerBackward'
            );

        $after =
            $component->get('elements');

        $this->assertSame(
            $qrId,
            $after[0]['id']
        );
    }

    public function test_layer_boundaries_do_not_reorder_elements(): void
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

        $initial =
            $component->get('elements');

        $firstId = $initial[0]['id'];
        $lastId = $initial[1]['id'];

        $component
            ->call(
                'selectElement',
                $firstId
            )
            ->call(
                'moveLayerBackward'
            );

        $this->assertSame(
            $initial,
            $component->get('elements')
        );

        $component
            ->call(
                'selectElement',
                $lastId
            )
            ->call(
                'moveLayerForward'
            );

        $this->assertSame(
            $initial,
            $component->get('elements')
        );
    }


    public function test_add_page_creates_and_activates_next_page(): void
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
            ->call(
                'addPage',
                'Back Side'
            );

        $pages = $component->get('pages');

        $this->assertCount(
            2,
            $pages
        );

        $this->assertSame(
            1,
            $pages[0]['page_number']
        );

        $this->assertSame(
            2,
            $pages[1]['page_number']
        );

        $this->assertSame(
            'Back Side',
            $pages[1]['name']
        );

        $component
            ->assertSet(
                'activePageId',
                $pages[1]['id']
            )
            ->assertSet(
                'elements',
                []
            )
            ->assertSet(
                'selectedElementId',
                null
            )
            ->assertSet(
                'isDirty',
                false
            );
    }

    public function test_rename_active_page_updates_component_and_database(): void
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
            ->call(
                'renameActivePage',
                'Ticket Front'
            )
            ->assertSet(
                'pages.0.name',
                'Ticket Front'
            );

        $pageId =
            $component->get('activePageId');

        $this->assertDatabaseHas(
            'ticket_template_pages',
            [
                'id' => $pageId,
                'organization_ticket_template_id' =>
                    $templateId,
                'name' => 'Ticket Front',
            ]
        );
    }

    public function test_multiple_new_pages_receive_sequential_page_numbers(): void
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
            ->call(
                'addPage',
                'Back'
            )
            ->call(
                'addPage',
                'Terms'
            );

        $pages =
            $component->get('pages');

        $this->assertSame(
            [1, 2, 3],
            array_column(
                $pages,
                'page_number'
            )
        );

        $this->assertSame(
            ['Page 1', 'Back', 'Terms'],
            array_column(
                $pages,
                'name'
            )
        );
    }

    public function test_switching_pages_keeps_each_page_definition_independent(): void
    {
        $organization =
            Organization::query()->create([
                'name' => 'Organization A',
            ]);

        $template =
            OrganizationTicketTemplate::query()->create([
                'organization_id' =>
                    $organization->id,
                'name' => 'Multi Page Template',
                'width' => 1080,
                'height' => 1350,
                'is_active' => true,
                'is_default' => false,
            ]);

        $front =
            TicketTemplatePage::query()->create([
                'organization_ticket_template_id' =>
                    $template->id,
                'event_ticket_template_id' => null,
                'page_number' => 1,
                'name' => 'Front',
                'definition' => [
                    'version' => 1,
                    'elements' => [
                        [
                            'id' => 'front_name',
                            'type' => 'text',
                            'binding' => 'holder_name',
                        ],
                    ],
                ],
            ]);

        $back =
            TicketTemplatePage::query()->create([
                'organization_ticket_template_id' =>
                    $template->id,
                'event_ticket_template_id' => null,
                'page_number' => 2,
                'name' => 'Back',
                'definition' => [
                    'version' => 1,
                    'elements' => [
                        [
                            'id' => 'back_qr',
                            'type' => 'qr',
                            'binding' => 'ticket_qr',
                        ],
                    ],
                ],
            ]);

        $component = Livewire::test(
            TicketDesigner::class,
            [
                'templateType' =>
                    TicketDesignerService::TYPE_ORGANIZATION,
                'templateId' =>
                    $template->id,
            ]
        );

        $component
            ->assertSet(
                'activePageId',
                $front->id
            )
            ->assertSet(
                'elements.0.id',
                'front_name'
            )
            ->call(
                'switchPage',
                $back->id
            )
            ->assertSet(
                'activePageId',
                $back->id
            )
            ->assertSet(
                'elements.0.id',
                'back_qr'
            )
            ->call(
                'switchPage',
                $front->id
            )
            ->assertSet(
                'elements.0.id',
                'front_name'
            );
    }

    public function test_rename_rejects_empty_page_name(): void
    {
        [$templateType, $templateId] =
            $this->makeOrganizationTemplateContext();

        $component = Livewire::test(
            TicketDesigner::class,
            [
                'templateType' => $templateType,
                'templateId' => $templateId,
            ]
        );

        $before =
            $component->get('pages');

        $component
            ->call(
                'renameActivePage',
                '   '
            )
            ->assertHasErrors(
                'pageName'
            );

        $this->assertSame(
            $before,
            $component->get('pages')
        );
    }


    public function test_save_persists_active_page_definition(): void
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

        $pageId =
            $component->get('activePageId');

        $elements =
            $component->get('elements');

        $component
            ->call('save')
            ->assertSet(
                'isDirty',
                false
            );

        $page =
            TicketTemplatePage::query()
                ->findOrFail($pageId);

        $this->assertSame(
            1,
            $page->definition['version']
        );

        $this->assertSame(
            $elements,
            $page->definition['elements']
        );
    }

    public function test_save_preserves_element_order(): void
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

        $before =
            $component->get('elements');

        $component
            ->call(
                'selectElement',
                $before[0]['id']
            )
            ->call('moveLayerForward')
            ->call('save');

        $expected =
            $component->get('elements');

        $page =
            TicketTemplatePage::query()
                ->findOrFail(
                    $component->get(
                        'activePageId'
                    )
                );

        $this->assertSame(
            array_column(
                $expected,
                'id'
            ),
            array_column(
                $page->definition['elements'],
                'id'
            )
        );
    }

    public function test_invalid_definition_is_not_persisted(): void
    {
        [$templateType, $templateId] =
            $this->makeOrganizationTemplateContext();

        $component = Livewire::test(
            TicketDesigner::class,
            [
                'templateType' => $templateType,
                'templateId' => $templateId,
            ]
        );

        $pageId =
            $component->get('activePageId');

        $page =
            TicketTemplatePage::query()
                ->findOrFail($pageId);

        $originalDefinition =
            $page->definition;

        $component
            ->set(
                'elements',
                [
                    [
                        'id' => 'bad_001',
                        'type' => 'javascript',
                        'x' => 100,
                        'y' => 100,
                        'width' => 200,
                        'height' => 100,
                        'rotation' => 0,
                    ],
                ]
            )
            ->call('save')
            ->assertHasErrors(
                'definition'
            );

        $page->refresh();

        $this->assertSame(
            $originalDefinition,
            $page->definition
        );
    }

    public function test_failed_save_keeps_designer_dirty(): void
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
            ->set(
                'elements.0.type',
                'unsupported_type'
            )
            ->call('save')
            ->assertHasErrors(
                'definition'
            )
            ->assertSet(
                'isDirty',
                true
            );
    }

    public function test_save_cannot_target_page_from_another_template(): void
    {
        [$templateType, $templateId] =
            $this->makeOrganizationTemplateContext();

        $otherOrganization =
            Organization::query()->create([
                'name' => 'Organization B',
            ]);

        $otherTemplate =
            OrganizationTicketTemplate::query()->create([
                'organization_id' =>
                    $otherOrganization->id,
                'name' => 'Other Template',
                'width' => 1080,
                'height' => 1350,
                'is_active' => true,
                'is_default' => false,
            ]);

        $foreignPage =
            TicketTemplatePage::query()->create([
                'organization_ticket_template_id' =>
                    $otherTemplate->id,
                'event_ticket_template_id' => null,
                'page_number' => 1,
                'name' => 'Foreign Page',
                'definition' => [
                    'version' => 1,
                    'elements' => [],
                ],
            ]);

        $originalDefinition =
            $foreignPage->definition;

        $component = Livewire::test(
            TicketDesigner::class,
            [
                'templateType' => $templateType,
                'templateId' => $templateId,
            ]
        )
            ->call('addText')
            ->set(
                'activePageId',
                $foreignPage->id
            )
            ->call('save')
            ->assertHasErrors(
                'activePageId'
            );

        $foreignPage->refresh();

        $this->assertSame(
            $originalDefinition,
            $foreignPage->definition
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
