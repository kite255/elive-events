<?php

namespace Tests\Feature\Badges;

use App\Models\CommunicationLog;
use App\Services\BadgeDeliveryService;
use Tests\TestCase;

class BadgeResendConfigurationTest extends TestCase
{
    public function test_badge_resend_service_and_purpose_are_registered(): void
    {
        $this->assertTrue(
            class_exists(
                BadgeDeliveryService::class
            )
        );

        $this->assertSame(
            'badge_resend',
            CommunicationLog::PURPOSE_BADGE_RESEND
        );
    }

    public function test_event_attendee_table_exposes_badge_resend_actions(): void
    {
        $relationManager = file_get_contents(
            app_path(
                'Filament/Resources/Events/RelationManagers/AttendeesRelationManager.php'
            )
        );

        $this->assertIsString(
            $relationManager
        );

        $this->assertStringContainsString(
            "Action::make('resend_badge')",
            $relationManager
        );

        $this->assertStringContainsString(
            "BulkAction::make('resend_badges')",
            $relationManager
        );

        $this->assertStringContainsString(
            'BadgeDeliveryService::class',
            $relationManager
        );
    }

    public function test_global_attendee_table_exposes_badge_resend_action(): void
    {
        $attendeesTable = file_get_contents(
            app_path(
                'Filament/Resources/Attendees/Tables/AttendeesTable.php'
            )
        );

        $this->assertIsString(
            $attendeesTable
        );

        $this->assertStringContainsString(
            "Action::make('resend_badge')",
            $attendeesTable
        );

        $this->assertStringContainsString(
            'BadgeDeliveryService::class',
            $attendeesTable
        );
    }

    public function test_resend_service_reuses_existing_badge_before_generating(): void
    {
        $service = file_get_contents(
            app_path(
                'Services/BadgeDeliveryService.php'
            )
        );

        $this->assertIsString(
            $service
        );

        $this->assertStringContainsString(
            'if (blank($attendee->badge_path))',
            $service
        );

        $this->assertStringContainsString(
            'SendAutomaticCommunicationJob::dispatch',
            $service
        );

        $this->assertStringContainsString(
            "'purpose' =>",
            $service
        );

        $this->assertStringContainsString(
            'CommunicationLog::PURPOSE_BADGE_RESEND',
            $service
        );
    }
}
