<?php

namespace Tests\Feature\Events;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventMediaFormTest extends TestCase
{
    #[Test]
    public function event_form_has_global_event_media_section(): void
    {
        $source = file_get_contents(
            app_path(
                'Filament/Resources/Events/Schemas/EventForm.php'
            )
        );

        $this->assertStringContainsString(
            "Section::make('Event Media')",
            $source
        );

        $this->assertStringContainsString(
            "->label('Event Cover Image')",
            $source
        );

        $this->assertStringContainsString(
            "->label('Event Logo')",
            $source
        );
    }

    #[Test]
    public function event_media_reuses_existing_media_columns(): void
    {
        $source = file_get_contents(
            app_path(
                'Filament/Resources/Events/Schemas/EventForm.php'
            )
        );

        $this->assertStringContainsString(
            "registration_banner_image_path",
            $source
        );

        $this->assertStringContainsString(
            "registration_logo_path",
            $source
        );
    }

    #[Test]
    public function registration_branding_remains_separate_from_event_media(): void
    {
        $source = file_get_contents(
            app_path(
                'Filament/Resources/Events/Schemas/EventForm.php'
            )
        );

        $this->assertStringContainsString(
            "Section::make('Registration Branding')",
            $source
        );
    }
}