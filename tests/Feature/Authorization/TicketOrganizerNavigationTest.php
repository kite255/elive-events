<?php

namespace Tests\Feature\Authorization;

use App\Filament\Pages\AdminTicketLookup;
use App\Filament\Pages\AttendanceDashboard;
use App\Filament\Pages\AttendanceReport;
use App\Filament\Pages\AttendeeReport;
use App\Filament\Pages\BadgePrintReport;
use App\Filament\Pages\BadgePrintStation;
use App\Filament\Pages\CheckInStation;
use App\Filament\Pages\CommunicationCenter;
use App\Filament\Pages\GateCheckIn;
use App\Filament\Pages\ManualCheckIn;
use App\Filament\Pages\OrganizerFinanceOverview;
use App\Filament\Pages\OrganizerSalesDashboard;
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
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\TicketOrders\TicketOrderResource;
use App\Filament\Resources\TicketTypes\TicketTypeResource;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrganizerNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_organizer_only_registers_approved_ticketing_navigation(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organizer A',
            'email' => 'orga@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Organizer A',
            'email' => 'organizer-a@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $organizer->organizations()->attach(
            $organization->id,
            [
                'role' => User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $this->actingAs($organizer);

        /*
        |--------------------------------------------------------------------------
        | Approved Ticket Organizer Navigation
        |--------------------------------------------------------------------------
        */

        $approvedNavigation = [
            OrganizerFinanceOverview::class,
            OrganizerSalesDashboard::class,
            AdminTicketLookup::class,
            PaymentResource::class,
            TicketTypeResource::class,
            TicketOrderResource::class,
        ];

        foreach ($approvedNavigation as $class) {
            $this->assertTrue(
                $class::shouldRegisterNavigation(),
                "{$class} should appear for a Ticket Organizer."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Forbidden Ticket Organizer Navigation
        |--------------------------------------------------------------------------
        */

        $forbiddenNavigation = [
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

        foreach ($forbiddenNavigation as $class) {
            $this->assertFalse(
                $class::shouldRegisterNavigation(),
                "{$class} must not appear for a Ticket Organizer."
            );
        }
    }
}