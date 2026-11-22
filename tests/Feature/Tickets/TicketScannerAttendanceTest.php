<?php

namespace Tests\Feature\Tickets;

use App\Filament\Gate\GateTicketScanner;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\TicketScanner;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\EventStaffRelationManager;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Tickets\TicketCheckInService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TicketScannerAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_lists_pending_and_checked_in_tickets_separately(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        ['event' => $event, 'ticket' => $ticket] = $this->createIssuedTicket();
        ['ticket' => $anotherTicket] = $this->createIssuedTicket();

        $scanner = Livewire::test(TicketScanner::class)
            ->assertSee('Checked In')
            ->assertSee('Pending')
            ->call('showAttendance', 'pending')
            ->set('attendanceEventId', (string) $event->id)
            ->assertSee($ticket->ticket_number)
            ->assertDontSee($anotherTicket->ticket_number)
            ->set('attendanceSearch', 'no matching ticket')
            ->assertDontSee($ticket->ticket_number)
            ->set('attendanceSearch', 'Ticket Buyer')
            ->assertSee($ticket->ticket_number)
            ->call('showAttendance', 'checked_in')
            ->assertDontSee($ticket->ticket_number);

        $ticket->update(['status' => Ticket::STATUS_USED, 'used_at' => now()]);

        $scanner->call('$refresh')
            ->assertSee($ticket->ticket_number)
            ->call('showAttendance', 'pending')
            ->assertDontSee($ticket->ticket_number);

        $ticket->update(['status' => Ticket::STATUS_CANCELLED, 'used_at' => null]);

        $scanner->call('$refresh')->assertDontSee($ticket->ticket_number);
    }

    public function test_gate_staff_can_only_list_tickets_for_an_authorized_event(): void
    {
        $this->withoutVite();
        ['organization' => $organization, 'event' => $event, 'ticket' => $ticket] = $this->createIssuedTicket();
        ['event' => $otherEvent, 'ticket' => $otherTicket] = $this->createIssuedTicket();

        $otherEvent->update(['name' => 'Unassigned concert']);
        $officer = User::factory()->create();
        $officer->organizations()->attach($organization->id, [
            'role' => User::ORGANIZATION_ROLE_CHECK_IN_OFFICER,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
        ]);
        $event->users()->attach($officer->id, [
            'role' => User::ORGANIZATION_ROLE_CHECK_IN_OFFICER,
            'status' => Event::STAFF_STATUS_ACTIVE,
        ]);
        $this->actingAs($officer);

        Livewire::test(TicketScanner::class)
            ->call('showAttendance', 'pending')
            ->assertSee($ticket->ticket_number)
            ->assertDontSee($otherEvent->name)
            ->set('attendanceEventId', (string) $otherEvent->id)
            ->assertDontSee($otherTicket->ticket_number)
            ->assertDontSee($ticket->ticket_number);
    }

    public function test_scanner_returns_the_real_ticket_type_for_a_check_in_and_repeat_scan(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        ['ticket' => $ticket, 'ticketType' => $type, 'rawQrToken' => $credential] = $this->createIssuedTicket();
        $type->update(['name' => 'Premium Access']);

        $this->postJson(route('admin.ticket-scanner.scan'), ['qr_token' => $credential])
            ->assertOk()
            ->assertJsonPath('status', 'checked_in')
            ->assertJsonPath('ticket.ticket_number', $ticket->ticket_number)
            ->assertJsonPath('ticket_type', 'Premium Access');

        $this->postJson(route('admin.ticket-scanner.scan'), ['qr_token' => $ticket->ticket_number])
            ->assertOk()
            ->assertJsonPath('status', 'already_used')
            ->assertJsonPath('ticket_type', 'Premium Access')
            ->assertJsonStructure(['checked_in_at']);

        $this->assertDatabaseCount('ticket_check_ins', 1);
    }

    public function test_event_administrator_can_create_a_gate_account_from_event_staff(): void
    {
        $this->withoutVite();
        ['organization' => $organization, 'event' => $event] = $this->createIssuedTicket();
        $administrator = User::factory()->create();
        $administrator->organizations()->attach($organization->id, [
            'role' => User::ORGANIZATION_ROLE_ADMIN,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
        ]);
        $this->actingAs($administrator);

        $staff = Livewire::test(EventStaffRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditEvent::class,
        ]);

        $staff->callAction(TestAction::make('create_gate_staff')->table(), data: [
            'name' => 'Front Gate Operator',
            'email' => 'gate@example.test',
            'password' => 'strong-test-password',
            'password_confirmation' => 'strong-test-password',
        ])->assertHasNoActionErrors();

        $gateStaff = User::query()->where('email', 'gate@example.test')->sole();
        $this->assertTrue(Hash::check('strong-test-password', $gateStaff->password));
        $this->assertSame([$event->id], $gateStaff->checkInEvents()->pluck('events.id')->all());
        $this->assertTrue($gateStaff->hasActiveOrganizationAccess($organization->id));
        $this->assertFalse($gateStaff->isSuperAdmin());

        $staff->callAction(TestAction::make('create_gate_staff')->table(), data: [
            'name' => 'Another Operator',
            'email' => 'GATE@example.test',
            'password' => 'another-strong-password',
            'password_confirmation' => 'another-strong-password',
        ])->assertHasActionErrors(['email']);

        $this->assertTrue(Hash::check('strong-test-password', $gateStaff->fresh()->password));
    }

    public function test_gate_staff_can_sign_in_and_land_on_the_scanner(): void
    {
        $this->withoutVite();
        ['organization' => $organization, 'event' => $event] = $this->createIssuedTicket();
        $gateStaff = User::factory()->create(['is_gate_staff' => true, 'password' => 'strong-test-password']);
        $gateStaff->organizations()->attach($organization->id, [
            'role' => User::ORGANIZATION_ROLE_MEMBER,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
        ]);
        $event->assignUser($gateStaff, User::ORGANIZATION_ROLE_CHECK_IN_OFFICER);

        $this->get('/gate/ticket-scanner')->assertRedirect('/gate/login');
        $this->get('/gate/login')->assertOk();

        Filament::setCurrentPanel(Filament::getPanel('gate'));
        $this->withSession(['url.intended' => url('/admin/events')]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $gateStaff->email, 'password' => 'strong-test-password'])
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect('/gate');

        $this->assertAuthenticatedAs($gateStaff, 'gate');
        $this->assertGuest('web');
        $this->get('/gate')->assertRedirect('/gate/ticket-scanner');
        $this->get('/gate/ticket-scanner')->assertOk();

        $event->suspendAssignedUser($gateStaff);

        $this->get('/gate/login')->assertOk();
        $this->assertGuest('gate');
    }

    public function test_gate_account_only_accesses_the_scanner_and_assigned_event(): void
    {
        $this->withoutVite();
        ['organization' => $organization, 'event' => $event, 'ticket' => $ticket] = $this->createIssuedTicket();
        ['event' => $otherEvent, 'ticket' => $otherTicket] = $this->createIssuedTicket();
        $otherEvent->update(['name' => 'Unassigned Concert', 'organization_id' => $organization->id]);
        $gateStaff = User::factory()->create(['is_gate_staff' => true]);
        $gateStaff->organizations()->attach($organization->id, [
            'role' => User::ORGANIZATION_ROLE_MEMBER,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
        ]);
        $event->assignUser($gateStaff, User::ORGANIZATION_ROLE_CHECK_IN_OFFICER);

        $this->actingAs($gateStaff, 'web');
        $this->get('/admin')->assertForbidden();
        $this->get('/admin/ticket-scanner')->assertForbidden();
        $this->postJson('/admin/ticket-scanner/scan', ['qr_token' => $ticket->ticket_number])
            ->assertForbidden();

        $this->actingAs($gateStaff, 'gate');
        $this->get('/gate/ticket-scanner')->assertOk()->assertSee($event->name);
        $this->get('/gate/events')->assertNotFound();

        Livewire::test(GateTicketScanner::class)
            ->call('showAttendance', 'pending')
            ->assertSee($ticket->ticket_number)
            ->assertDontSee($otherEvent->name)
            ->set('attendanceEventId', (string) $otherEvent->id)
            ->assertDontSee($otherTicket->ticket_number);

        $this->postJson('/gate/ticket-scanner/scan', ['qr_token' => $otherTicket->ticket_number])
            ->assertForbidden();
        $this->postJson('/gate/ticket-scanner/scan', ['qr_token' => $ticket->ticket_number])
            ->assertOk()
            ->assertJsonPath('status', 'checked_in')
            ->assertJsonMissingPath('ticket.holder_email');
        $this->assertDatabaseHas('ticket_check_ins', [
            'ticket_id' => $ticket->id,
            'checked_in_by' => $gateStaff->id,
        ]);

        $event->suspendAssignedUser($gateStaff);

        $this->get('/gate/ticket-scanner')->assertForbidden();
        $this->postJson('/gate/ticket-scanner/scan', ['qr_token' => $ticket->ticket_number])
            ->assertForbidden();
        $this->assertDatabaseCount('ticket_check_ins', 1);
    }

    public function test_event_editor_can_generate_a_shareable_scanner_link(): void
    {
        $this->withoutVite();
        ['event' => $event] = $this->createIssuedTicket();
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $editor = Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->assertActionVisible('share_scanner_link')
            ->mountAction('share_scanner_link');

        $link = $editor->get('mountedActions.0.data.url');
        $this->assertIsString($link);
        parse_str(parse_url($link, PHP_URL_QUERY), $query);
        $this->assertEqualsWithDelta(now()->addHours(12)->timestamp, $query['expires'], 5);

        auth()->logout();
        $this->get($link)->assertOk()->assertSee($event->name)
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_shared_scanner_accepts_only_tickets_for_its_signed_event(): void
    {
        $this->withoutVite();
        ['event' => $event, 'ticket' => $ticket, 'rawQrToken' => $qr] = $this->createIssuedTicket();
        ['event' => $otherEvent, 'ticket' => $otherTicket, 'rawQrToken' => $otherQr] = $this->createIssuedTicket();
        $otherEvent->update(['name' => 'Another event']);
        $endpoint = route('gate.shared-scanner', $event, absolute: false);
        $link = URL::temporarySignedRoute('gate.shared-scanner', now()->addHour(), $event, absolute: false);

        $this->get($endpoint)->assertForbidden();
        $this->postJson($endpoint, ['qr_token' => $qr])->assertForbidden();
        $this->get($link)->assertOk()
            ->assertSee($event->name)
            ->assertSee('elive-scanner--shared', false)
            ->assertSee('eLive-Logo.png')
            ->assertDontSee('Confirm Check-in')
            ->assertSee('Ready for next ticket')
            ->assertSee('Checked in at')
            ->assertDontSee('Undo Check-in')
            ->assertDontSee('Mark Unused')
            ->assertDontSee('Another event')
            ->assertDontSee('Checked In', false);
        $this->assertGuest();

        foreach ([$otherQr, $otherTicket->ticket_number] as $unalignedTicket) {
            $this->postJson($link, ['qr_token' => $unalignedTicket])
                ->assertOk()->assertJsonPath('status', 'ticket_not_found');
        }

        $this->postJson($link, ['qr_token' => $qr])
            ->assertOk()
            ->assertJsonPath('status', 'checked_in')
            ->assertJsonPath('ticket.ticket_number', $ticket->ticket_number)
            ->assertJsonPath('ticket_type', 'Regular')
            ->assertJsonStructure(['checked_in_time_eat'])
            ->assertJsonPath('ticket.holder_name', $ticket->holder_name)
            ->assertJsonMissingPath('ticket.holder_email')
            ->assertJsonMissingPath('ticket.holder_phone');
        $this->postJson($link, ['qr_token' => $ticket->ticket_number])
            ->assertOk()
            ->assertJsonPath('status', 'already_used')
            ->assertJsonPath('ticket.holder_name', $ticket->holder_name);
        $this->assertDatabaseCount('ticket_check_ins', 1);
        $this->assertDatabaseHas('ticket_check_ins', [
            'ticket_id' => $ticket->id,
            'event_id' => $event->id,
            'checked_in_by' => null,
        ]);
    }

    public function test_shared_scanner_rejects_expired_or_modified_links(): void
    {
        ['event' => $event, 'rawQrToken' => $qr] = $this->createIssuedTicket();
        ['event' => $otherEvent] = $this->createIssuedTicket();
        $expired = URL::temporarySignedRoute('gate.shared-scanner', now()->subMinute(), $event, absolute: false);
        $valid = URL::temporarySignedRoute('gate.shared-scanner', now()->addHour(), $event, absolute: false);
        $modified = str_replace("/{$event->id}?", "/{$otherEvent->id}?", $valid);

        $this->get($expired)->assertForbidden();
        $this->postJson($expired, ['qr_token' => $qr])->assertForbidden();
        $this->get($modified)->assertForbidden();
        $this->postJson($modified, ['qr_token' => $qr])->assertForbidden();
        $this->assertDatabaseCount('ticket_check_ins', 0);
    }

    public function test_shared_scanner_cannot_reverse_a_check_in_even_with_a_previous_undo_token(): void
    {
        ['event' => $event, 'ticket' => $ticket, 'rawQrToken' => $qr] = $this->createIssuedTicket();
        $link = URL::temporarySignedRoute('gate.shared-scanner', now()->addHour(), $event, absolute: false);
        parse_str(parse_url($link, PHP_URL_QUERY), $query);

        $this->postJson($link, ['qr_token' => $qr])
            ->assertJsonPath('status', 'checked_in')
            ->assertJsonMissingPath('undo_token');
        $token = Str::random(48);
        $this->withSession(['shared_scanner_undo' => [
            'token' => $token,
            'signature' => $query['signature'],
            'event_id' => $event->id,
            'ticket_id' => $ticket->id,
            'check_in_id' => $ticket->checkIns()->sole()->id,
        ]])->postJson($link, ['undo_token' => $token])->assertForbidden();

        $this->assertSame(Ticket::STATUS_USED, $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_check_ins', ['ticket_id' => $ticket->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'ticket.check_in_undone']);
    }

    public function test_event_manager_cannot_mark_a_used_ticket_unused(): void
    {
        $this->withoutVite();
        ['organization' => $organization, 'event' => $event, 'ticket' => $ticket, 'rawQrToken' => $qr] = $this->createIssuedTicket();
        $manager = User::factory()->create();
        $manager->organizations()->attach($organization->id, [
            'role' => User::ORGANIZATION_ROLE_EVENT_MANAGER,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
        ]);
        $event->assignUser($manager, User::ORGANIZATION_ROLE_EVENT_MANAGER);
        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $service = app(TicketCheckInService::class);
        $service->checkInByCredential($qr);

        Livewire::test(ListTickets::class)
            ->assertActionHidden(TestAction::make('undo_check_in')->table($ticket));
        $this->assertFalse($service->undoCheckIn($event->id, $ticket->id, actor: $manager));
        $this->assertSame(Ticket::STATUS_USED, $ticket->fresh()->status);
    }

    public function test_event_admin_can_reset_an_earlier_ticket_check_in_from_all_tickets(): void
    {
        $this->withoutVite();
        ['organization' => $organization, 'event' => $event, 'ticket' => $ticket, 'rawQrToken' => $qr] = $this->createIssuedTicket();
        $link = URL::temporarySignedRoute('gate.shared-scanner', now()->addHour(), $event, absolute: false);
        $this->postJson($link, ['qr_token' => $qr])->assertJsonPath('status', 'checked_in');
        $administrator = User::factory()->create();
        $administrator->organizations()->attach($organization->id, [
            'role' => User::ORGANIZATION_ROLE_ADMIN,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
        ]);
        $this->actingAs($administrator);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListTickets::class)
            ->callAction(TestAction::make('undo_check_in')->table($ticket))
            ->assertHasNoActionErrors();

        $this->assertSame(Ticket::STATUS_ISSUED, $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->used_at);
        $this->assertDatabaseMissing('ticket_check_ins', ['ticket_id' => $ticket->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket.check_in_undone',
            'subject_id' => $ticket->id,
            'actor_id' => $administrator->id,
        ]);

        $audit = AuditLog::query()->where('action', 'ticket.check_in_undone')->sole();
        Livewire::test(ListAuditLogs::class)
            ->searchTable($ticket->order->order_number)
            ->assertCanSeeTableRecords([$audit]);

        $this->postJson($link, ['qr_token' => $qr])->assertJsonPath('status', 'checked_in');
    }

    public function test_admin_confirmation_cannot_undo_a_replacement_check_in(): void
    {
        $this->withoutVite();
        ['event' => $event, 'ticket' => $ticket, 'rawQrToken' => $qr] = $this->createIssuedTicket();
        $administrator = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($administrator);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $service = app(TicketCheckInService::class);
        $service->checkInByCredential($qr);
        $list = Livewire::test(ListTickets::class)->mountAction(TestAction::make('undo_check_in')->table($ticket));

        $this->assertTrue($service->undoCheckIn($event->id, $ticket->id, actor: $administrator));
        $service->checkInByCredential($qr);
        $list->callMountedAction()->assertHasNoActionErrors();

        $this->assertSame(Ticket::STATUS_USED, $ticket->fresh()->status);
        $this->assertDatabaseCount('ticket_check_ins', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    private function createIssuedTicket(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Ticket Check-In Organization',
            'slug' => 'ticket-check-in-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Ticket Check-In Concert',
            'slug' => 'ticket-check-in-concert-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $ticketType = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'code' => 'REG',
            'price' => 1000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ORD-CHECKIN-' . uniqid(),
            'buyer_name' => 'Ticket Buyer',
            'buyer_phone' => '255700000001',
            'buyer_email' => 'buyer@example.com',
            'quantity' => 1,
            'subtotal' => 1000,
            'discount_amount' => 0,
            'total' => 1000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $item = TicketOrderItem::query()->create([
            'ticket_order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
            'discount_amount' => 0,
            'total' => 1000,
        ]);

        $rawQrToken = Str::random(64);

        $ticket = Ticket::query()->create([
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'ticket_order_item_id' => $item->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => 'ELV-REG-' . Str::upper(Str::random(8)),
            'public_token' => Str::random(40),
            'qr_token_encrypted' => $rawQrToken,
            'qr_token_hash' => hash(
                'sha256',
                $rawQrToken
            ),
            'holder_name' => 'Ticket Buyer',
            'holder_phone' => '255700000001',
            'holder_email' => 'buyer@example.com',
            'price' => 1000,
            'currency' => 'TZS',
            'status' => Ticket::STATUS_ISSUED,
            'issued_at' => now(),
        ]);

        return compact(
            'organization',
            'event',
            'ticketType',
            'order',
            'item',
            'ticket',
            'rawQrToken'
        );
    }
}
