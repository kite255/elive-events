<?php

namespace Tests\Feature\Tickets;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TicketTemplateSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_template_tables_and_required_columns_exist(): void
    {
        $this->assertTrue(
            Schema::hasColumns('organization_ticket_templates', [
                'id',
                'organization_id',
                'name',
                'description',
                'width',
                'height',
                'thumbnail_path',
                'is_active',
                'is_default',
                'created_at',
                'updated_at',
            ])
        );

        $this->assertTrue(
            Schema::hasColumns('event_ticket_templates', [
                'id',
                'event_id',
                'ticket_type_id',
                'source_organization_template_id',
                'name',
                'width',
                'height',
                'thumbnail_path',
                'is_active',
                'is_default',
                'created_at',
                'updated_at',
            ])
        );

        $this->assertTrue(
            Schema::hasColumns('ticket_template_pages', [
                'id',
                'organization_ticket_template_id',
                'event_ticket_template_id',
                'page_number',
                'name',
                'background_image_path',
                'definition',
                'created_at',
                'updated_at',
            ])
        );

        $this->assertTrue(
            Schema::hasColumns('ticket_template_assets', [
                'id',
                'organization_id',
                'organization_ticket_template_id',
                'event_ticket_template_id',
                'asset_type',
                'label',
                'file_path',
                'mime_type',
                'width',
                'height',
                'metadata',
                'created_at',
                'updated_at',
            ])
        );
    }
}
