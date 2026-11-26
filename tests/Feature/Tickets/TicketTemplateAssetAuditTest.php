<?php

namespace Tests\Feature\Tickets;

use App\Models\Organization;
use App\Models\OrganizationTicketTemplate;
use App\Models\TicketTemplateAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketTemplateAssetAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_reports_invalid_layout_and_missing_asset_without_mutation(): void
    {
        Storage::fake('public');
        $organization = Organization::create(['name' => 'Audit organization']);
        $template = OrganizationTicketTemplate::create([
            'organization_id' => $organization->id, 'name' => 'Audit',
            'width' => 1280, 'height' => 466, 'is_active' => true, 'is_default' => false,
        ]);
        $missing = 'ticket-template-backgrounds/organization/'.$template->id.'/missing.png';
        $page = $template->pages()->create([
            'page_number' => 1, 'name' => 'Front', 'background_image_path' => $missing,
            'definition' => ['version' => 1, 'elements' => [[
                'id' => 'qr', 'type' => 'qr', 'binding' => 'ticket_qr',
                'x' => 100, 'y' => 800, 'width' => 100, 'height' => 100,
            ]]],
        ]);
        $registered = 'ticket-template-elements/organization/'.$template->id.'/registered.png';
        $orphan = 'ticket-template-elements/organization/'.$template->id.'/orphan.png';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        Storage::disk('public')->put($registered, $png);
        Storage::disk('public')->put($orphan, $png);
        $asset = TicketTemplateAsset::create([
            'organization_id' => $organization->id,
            'organization_ticket_template_id' => $template->id,
            'asset_type' => 'logo', 'label' => 'Registered logo',
            'file_path' => $registered, 'mime_type' => 'image/png',
        ]);
        $before = $page->fresh()->getAttributes();
        $this->artisan('tickets:audit-template-assets')
            ->expectsOutputToContain('elements.0.position')
            ->expectsOutputToContain($missing)
            ->expectsOutputToContain($orphan)
            ->doesntExpectOutputToContain($registered)
            ->expectsOutputToContain('No files deleted or records modified')
            ->assertSuccessful();
        $this->assertSame($before, $page->fresh()->getAttributes());
        $this->assertNotNull($asset->fresh());
        $this->assertSame($png, Storage::disk('public')->get($registered));
        $this->assertSame($png, Storage::disk('public')->get($orphan));
        Storage::disk('public')->assertMissing($missing);
    }

    public function test_audit_rejects_invalid_detail_limit(): void
    {
        $this->artisan('tickets:audit-template-assets', ['--limit' => 0])
            ->expectsOutput('Limit must be between 1 and 1000.')
            ->assertExitCode(2);
    }
}
