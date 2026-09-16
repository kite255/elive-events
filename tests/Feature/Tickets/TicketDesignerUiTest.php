<?php

namespace Tests\Feature\Tickets;

use App\Livewire\Tickets\TicketDesigner;
use App\Models\Organization;
use App\Models\OrganizationTicketTemplate;
use App\Services\Tickets\TicketDesignerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketDesignerUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_designer_renders_main_workspace_regions(): void
    {
        [$type, $templateId] =
            $this->makeTemplate();

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' => $type,
                'templateId' => $templateId,
            ]
        )
            ->assertSeeHtml(
                'data-testid="ticket-designer-toolbar"'
            )
            ->assertSeeHtml(
                'data-testid="ticket-designer-tools"'
            )
            ->assertSeeHtml(
                'data-testid="ticket-designer-canvas"'
            )
            ->assertSeeHtml(
                'data-testid="ticket-designer-properties"'
            )
            ->assertSeeHtml(
                'data-testid="ticket-designer-pages"'
            );
    }

    public function test_tool_palette_contains_all_supported_element_actions(): void
    {
        [$type, $templateId] =
            $this->makeTemplate();

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' => $type,
                'templateId' => $templateId,
            ]
        )
            ->assertSeeHtml(
                'wire:click="addText"'
            )
            ->assertSeeHtml(
                'wire:click="addQr"'
            )
            ->assertSeeHtml(
                'wire:click="addImage"'
            )
            ->assertSeeHtml(
                'wire:click="addLogo"'
            )
            ->assertSeeHtml(
                'wire:click="addSponsorLogo"'
            )
            ->assertSeeHtml(
                'wire:click="addShape"'
            )
            ->assertSeeHtml(
                'wire:click="addLine"'
            );
    }

    public function test_toolbar_contains_save_action_and_status(): void
    {
        [$type, $templateId] =
            $this->makeTemplate();

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' => $type,
                'templateId' => $templateId,
            ]
        )
            ->assertSeeHtml(
                'wire:click="save"'
            )
            ->assertSee(
                'Saved'
            )
            ->call('addText')
            ->assertSee(
                'Unsaved changes'
            );
    }

    public function test_added_elements_are_rendered_on_canvas(): void
    {
        [$type, $templateId] =
            $this->makeTemplate();

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' => $type,
                'templateId' => $templateId,
            ]
        )
            ->call('addText')
            ->assertSeeHtml(
                'data-element-type="text"'
            )
            ->call('addQr')
            ->assertSeeHtml(
                'data-element-type="qr"'
            );
    }

    public function test_selected_element_renders_property_controls(): void
    {
        [$type, $templateId] =
            $this->makeTemplate();

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' => $type,
                'templateId' => $templateId,
            ]
        )
            ->call('addText')
            ->assertSeeHtml(
                'data-testid="selected-element-properties"'
            )
            ->assertSee(
                'Position'
            )
            ->assertSee(
                'Size'
            )
            ->assertSee(
                'Rotation'
            )
            ->assertSee(
                'Layer'
            );
    }

    public function test_pages_area_renders_existing_page_and_add_page_action(): void
    {
        [$type, $templateId] =
            $this->makeTemplate();

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' => $type,
                'templateId' => $templateId,
            ]
        )
            ->assertSee(
                'Page 1'
            )
            ->assertSeeHtml(
                'wire:click="addPage"'
            );
    }

    private function makeTemplate(): array
    {
        $organization =
            Organization::query()->create([
                'name' => 'Organization A',
            ]);

        $template =
            OrganizationTicketTemplate::query()->create([
                'organization_id' =>
                    $organization->id,
                'name' =>
                    'Visual Designer Template',
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