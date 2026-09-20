<?php

namespace Tests\Feature\Authorization;

use App\Filament\Pages\AttendanceDashboard;
use App\Filament\Pages\AttendanceReport;
use App\Filament\Pages\AttendeeReport;
use App\Filament\Pages\BadgePrintReport;
use App\Filament\Pages\BadgePrintStation;
use App\Filament\Pages\CheckInStation;
use App\Filament\Pages\CommunicationCenter;
use App\Filament\Pages\GateCheckIn;
use App\Filament\Pages\ManualCheckIn;
use App\Filament\Pages\TicketScanner;
use App\Filament\Resources\AttendeeCategories\AttendeeCategoryResource;
use App\Filament\Resources\AttendeeMerchandises\AttendeeMerchandiseResource;
use App\Filament\Resources\Attendees\AttendeeResource;
use App\Filament\Resources\BadgeTemplateElements\BadgeTemplateElementResource;
use App\Filament\Resources\BadgeTemplates\BadgeTemplateResource;
use App\Filament\Resources\BadgeTypes\BadgeTypeResource;
use App\Filament\Resources\CheckInPoints\CheckInPointResource;
use App\Filament\Resources\CommunicationCampaigns\CommunicationCampaignResource;
use App\Filament\Resources\CommunicationLogs\CommunicationLogResource;
use App\Filament\Resources\CommunicationTemplates\CommunicationTemplateResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrganizerDirectUrlAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_organizer_cannot_directly_open_forbidden_pages(): void
    {
        $organizer = $this->createTicketOrganizer();

        $this->actingAs($organizer);

        $forbiddenPages = [
            TicketScanner::class,
            AttendanceDashboard::class,
            AttendanceReport::class,
            AttendeeReport::class,
            BadgePrintReport::class,
            BadgePrintStation::class,
            CheckInStation::class,
            CommunicationCenter::class,
            GateCheckIn::class,
            ManualCheckIn::class,
        ];

        foreach ($forbiddenPages as $page) {
            $response = $this->get(
                $page::getUrl()
            );

            $this->assertContains(
                $response->getStatusCode(),
                [
                    403,
                    404,
                ],
                "{$page} must reject direct URL access for a Ticket Organizer."
            );
        }
    }

    public function test_ticket_organizer_cannot_directly_open_forbidden_resources(): void
    {
        $organizer = $this->createTicketOrganizer();

        $this->actingAs($organizer);

        $forbiddenResources = [
            AttendeeCategoryResource::class,
            AttendeeMerchandiseResource::class,
            AttendeeResource::class,

            BadgeTemplateElementResource::class,
            BadgeTemplateResource::class,
            BadgeTypeResource::class,

            CheckInPointResource::class,

            CommunicationCampaignResource::class,
            CommunicationLogResource::class,
            CommunicationTemplateResource::class,

            EventResource::class,
            OrganizationResource::class,
        ];

        foreach ($forbiddenResources as $resource) {
            $response = $this->get(
                $resource::getUrl('index')
            );

            $this->assertContains(
                $response->getStatusCode(),
                [
                    403,
                    404,
                ],
                "{$resource} must reject direct URL access for a Ticket Organizer."
            );
        }
    }

    private function createTicketOrganizer(): User
    {
        $organization = Organization::query()->create([
            'name' => 'Ticket Events Ltd',
            'email' => 'ticket-events@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Organizer',
            'email' => 'ticket.organizer@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $organizer->organizations()->attach(
            $organization->id,
            [
                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    User::ORGANIZATION_STATUS_ACTIVE,

                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        return $organizer;
    }
}