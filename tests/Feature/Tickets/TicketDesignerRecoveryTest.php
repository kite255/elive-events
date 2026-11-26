<?php

namespace Tests\Feature\Tickets;

use App\Livewire\Tickets\TicketDesigner;
use App\Models\Organization;
use App\Models\OrganizationTicketTemplate;
use App\Models\User;
use App\Services\Tickets\TicketTemplateDefinitionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketDesignerRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function template(): OrganizationTicketTemplate
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        $organization = Organization::create(['name' => 'Recovery organization']);
        return OrganizationTicketTemplate::create([
            'organization_id' => $organization->id, 'name' => 'Recovery',
            'width' => 1280, 'height' => 466, 'is_active' => true, 'is_default' => false,
        ]);
    }

    private function editor(OrganizationTicketTemplate $template)
    {
        return Livewire::test(TicketDesigner::class, [
            'templateType' => 'organization', 'templateId' => $template->id,
        ]);
    }

    public function test_recovery_is_unsaved_until_explicit_save(): void
    {
        $template = $this->template();
        $definition = ['version' => 1, 'elements' => [
            ['id' => 'qr', 'type' => 'qr', 'binding' => 'ticket_qr',
                'x' => 554.95, 'y' => 815.79, 'width' => 186.32, 'height' => 186.32],
            ['id' => 'number', 'type' => 'text', 'binding' => 'ticket_number',
                'x' => 610.58, 'y' => 1028.37, 'width' => 87.89, 'height' => 60.21,
                'style' => ['fontSize' => 48, 'color' => '#161943']],
        ]];
        $page = $template->pages()->create([
            'page_number' => 1, 'name' => 'Front', 'definition' => $definition,
        ]);
        $original = $page->fresh()->definition;
        $editor = $this->editor($template)->call('recoverOffCanvasElements')
            ->assertHasNoErrors()->assertSet('isDirty', true)
            ->assertSet('recoveryRevision', 1);
        $this->assertEquals($original, $page->fresh()->definition);
        $elements = $editor->get('elements');
        $this->assertEquals($elements[0]['width'], $elements[0]['height']);
        $this->assertEquals($definition['elements'][1]['style'], $elements[1]['style']);
        app(TicketTemplateDefinitionValidator::class)->validate(
            ['version' => 1, 'elements' => $elements], 1280, 466
        );
        $editor->call('save')->assertHasNoErrors()->assertSet('isDirty', false);
        $this->assertEquals($elements, $page->fresh()->definition['elements']);
    }

    public function test_recovery_leaves_visible_elements_unchanged(): void
    {
        $template = $this->template();
        $element = ['id' => 'text', 'type' => 'text', 'binding' => 'event_name',
            'x' => 20, 'y' => 20, 'width' => 200, 'height' => 80];
        $template->pages()->create([
            'page_number' => 1, 'name' => 'Front',
            'definition' => ['version' => 1, 'elements' => [$element]],
        ]);
        $this->editor($template)->call('recoverOffCanvasElements')
            ->assertHasNoErrors()->assertSet('isDirty', false)
            ->assertSet('recoveryRevision', 0)->assertSet('elements', [$element]);
    }

    public function test_recovery_rejects_stale_canvas_dimensions(): void
    {
        $template = $this->template();
        $editor = $this->editor($template);
        $template->update(['height' => 600]);
        $editor->call('recoverOffCanvasElements')->assertHasErrors(['definition'])
            ->assertSet('isDirty', false)->assertSet('recoveryRevision', 0);
    }
}
