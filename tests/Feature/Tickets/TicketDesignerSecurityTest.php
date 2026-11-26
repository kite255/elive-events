<?php

namespace Tests\Feature\Tickets;

use App\Livewire\Tickets\TicketDesigner;
use App\Models\Organization;
use App\Models\OrganizationTicketTemplate;
use App\Models\TicketTemplateAsset;
use App\Models\User;
use App\Services\Tickets\TicketDesignerService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TicketDesignerSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function template(): OrganizationTicketTemplate
    {
        $organization = Organization::create(['name' => 'Template security '.uniqid()]);
        return OrganizationTicketTemplate::create([
            'organization_id' => $organization->id,
            'name' => 'Security template',
            'width' => 1080,
            'height' => 1350,
            'is_active' => true,
            'is_default' => false,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($user);

        return $user;
    }

    private function definition(array $asset): array
    {
        return ['version' => 1, 'elements' => [[
            'id' => 'image_1', 'type' => 'image', 'x' => 10, 'y' => 10,
            'width' => 200, 'height' => 200,
            ...$asset,
        ]]];
    }

    private function rejectDefinition($page, array $asset): void
    {
        $before = $page->fresh()->definition;
        try {
            app(TicketDesignerService::class)->saveDefinition($page, $this->definition($asset));
            $this->fail('Unauthorized asset reference was saved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('elements.0.asset_path', $exception->errors());
        }
        $this->assertSame($before, $page->fresh()->definition);
    }

    public function test_guest_cannot_mount_direct_designer(): void
    {
        $template = $this->template();
        Livewire::test(TicketDesigner::class, [
            'templateType' => 'organization', 'templateId' => $template->id,
        ])->assertForbidden();
        $this->assertSame(0, $template->pages()->count());
    }

    public function test_gate_staff_cannot_mount_direct_designer_even_as_super_admin(): void
    {
        $template = $this->template();
        $actor = $this->admin();
        $actor->forceFill(['is_gate_staff' => true])->save();
        Livewire::test(TicketDesigner::class, [
            'templateType' => 'organization', 'templateId' => $template->id,
        ])->assertForbidden();
        $this->assertSame(0, $template->pages()->count());
    }

    public function test_revoked_access_blocks_subsequent_designer_request(): void
    {
        $template = $this->template();
        $actor = $this->admin();
        $component = Livewire::test(TicketDesigner::class, [
            'templateType' => 'organization', 'templateId' => $template->id,
        ])->assertOk();
        $actor->forceFill(['is_gate_staff' => true])->save();
        $component->call('addPage', 'Unauthorized')->assertForbidden();
        $this->assertSame(1, $template->pages()->count());
    }

    public function test_unrelated_user_cannot_mutate_page_through_service(): void
    {
        $this->admin();
        $template = $this->template();
        $service = app(TicketDesignerService::class);
        $page = $service->ensureInitialPage('organization', $template->id);
        $this->actingAs(User::factory()->create(['is_super_admin' => false]));

        try {
            $service->renamePage($page, 'Unauthorized');
            $this->fail('Unauthorized page rename succeeded.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('Page 1', $page->fresh()->name);
        }
    }

    public function test_foreign_and_unsafe_asset_paths_are_rejected(): void
    {
        Storage::fake('public');
        $this->admin();
        $template = $this->template();
        $other = $this->template();
        $page = app(TicketDesignerService::class)->ensureInitialPage('organization', $template->id);
        $foreign = 'ticket-template-elements/organization/'.$other->id.'/logo.png';
        Storage::disk('public')->put($foreign, 'test asset');
        foreach ([$foreign, '../private/logo.png', 'https://example.com/logo.png',
            'ticket-template-elements/organization/'.$template->id.'/missing.png'] as $path) {
            $this->rejectDefinition($page, ['asset_path' => $path]);
        }
        Storage::disk('public')->assertExists($foreign);
    }

    public function test_foreign_asset_id_is_rejected(): void
    {
        Storage::fake('public');
        $this->admin();
        $template = $this->template();
        $other = $this->template();
        $path = 'ticket-template-elements/organization/'.$other->id.'/logo.png';
        Storage::disk('public')->put($path, 'test asset');
        $asset = TicketTemplateAsset::create([
            'organization_id' => $other->organization_id,
            'organization_ticket_template_id' => $other->id,
            'asset_type' => 'logo', 'file_path' => $path,
            'mime_type' => 'image/png',
        ]);
        $page = app(TicketDesignerService::class)->ensureInitialPage('organization', $template->id);
        $this->rejectDefinition($page, ['asset_id' => $asset->id]);
    }

    public function test_owned_asset_and_existing_legacy_reference_can_be_saved(): void
    {
        Storage::fake('public');
        $this->admin();
        $template = $this->template();
        $service = app(TicketDesignerService::class);
        $page = $service->ensureInitialPage('organization', $template->id);
        $path = 'ticket-template-elements/organization/'.$template->id.'/logo.png';
        Storage::disk('public')->put($path, 'test asset');
        $definition = $this->definition(['asset_path' => $path]);
        $this->assertSame($definition, $service->saveDefinition($page, $definition)->definition);

        $legacy = 'ticket-template-elements/legacy.png';
        Storage::disk('public')->put($legacy, 'legacy asset');
        $definition = $this->definition(['asset_path' => $legacy]);
        $this->rejectDefinition($page, ['asset_path' => $legacy]);
        $page->update(['definition' => $definition]);
        $this->assertSame($definition, $service->saveDefinition($page, $definition)->definition);
    }
}
