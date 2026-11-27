<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Attendee;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\User;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Locked;

class EventWorkspace extends Page
{
    protected static string $resource = EventResource::class;

    protected string $view = 'filament.resources.events.pages.event-workspace';

    #[Locked]
    public int $eventId;

    public function mount(int|string $record): void
    {
        abort_unless(ctype_digit((string) $record), 404);
        $this->eventId = (int) $record;
        $this->event();
    }

    public static function authorizedEvents(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $query = Event::query()->accessibleBy($user);

        if ($user->is_gate_staff) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isTicketOrganizer()) {
            $query->whereIn('events.id', $user->assignedTicketingEventIds());
        }

        return $query;
    }

    public function event(): Event
    {
        $user = auth()->user();
        abort_unless($user instanceof User && ! $user->is_gate_staff, 403);

        return static::authorizedEvents($user)->whereKey($this->eventId)->firstOrFail();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->event()->name;
    }

    public function moduleLinks(): array
    {
        return static::linksFor($this->event());
    }

    public static function linksFor(Event $event): array
    {
        $user = auth()->user();
        abort_unless($user instanceof User && ! $user->is_gate_staff, 403);
        $event = static::authorizedEvents($user)->whereKey($event->id)->firstOrFail();
        $links = [];

        if ($event->canBeManagedBy($user)) {
            $links[] = [
                'label' => 'Event settings',
                'icon' => 'heroicon-o-cog-6-tooth',
                'url' => EventResource::getUrl('edit', ['record' => $event]),
            ];
        }

        if ($event->canBeCheckedInBy($user)
            && in_array($event->status, [Event::STATUS_ACTIVE, Event::STATUS_DRAFT], true)) {
            $links[] = [
                'label' => 'Check-in station',
                'icon' => 'heroicon-o-qr-code',
                'url' => \App\Filament\Pages\CheckInStation::getUrl(['event_id' => $event->id]),
            ];
        }

        if ($user->canManageEventCommunication($event)) {
            $links[] = [
                'label' => 'Communications',
                'icon' => 'heroicon-o-envelope',
                'url' => \App\Filament\Pages\CommunicationCenter::getUrl(['event_id' => $event->id]),
            ];
        }

        $ticketingConfigured = (bool) $event->ticketSetting?->ticket_sales_enabled
            || $event->ticketTypes()->exists()
            || $event->ticketOrders()->exists();

        $canManageTickets = $event->canBeManagedBy($user)
            || ($user->isTicketOrganizer() && $user->assignedTicketingEvents()->whereKey($event->id)->exists());

        if ($ticketingConfigured && $canManageTickets
            && \App\Filament\Resources\TicketOrders\TicketOrderResource::canViewAny()) {
            $links[] = [
                'label' => 'Ticket orders',
                'icon' => 'heroicon-o-ticket',
                'url' => \App\Filament\Resources\TicketOrders\TicketOrderResource::getUrl('index', [
                    'event_id' => $event->id,
                ]),
            ];
        }

        if ($ticketingConfigured && \App\Filament\Resources\TicketTypes\TicketTypeResource::authorizedEvents($user)->whereKey($event->id)->exists()) {
            $links[] = [
                'label' => 'Ticket types',
                'icon' => 'heroicon-o-ticket',
                'url' => \App\Filament\Resources\TicketTypes\TicketTypeResource::getUrl('index', ['event_id' => $event->id]),
            ];
        }

        if (($event->canManageRegistrationBy($user) || $event->canViewReportsBy($user))
            && \App\Filament\Resources\Attendees\AttendeeResource::canViewAny()) {
            $links[] = [
                'label' => 'Attendees',
                'icon' => 'heroicon-o-users',
                'url' => \App\Filament\Resources\Attendees\AttendeeResource::getUrl('index', [
                    'event_id' => $event->id,
                ]),
            ];
        }

        if ($event->canBeManagedBy($user)) {
            $relations = EventResource::getRelations();
            foreach ([
                ['Registration', 'heroicon-o-clipboard-document-list', \App\Filament\Resources\Events\RelationManagers\RegistrationFieldsRelationManager::class],
                ['Programme', 'heroicon-o-calendar-days', \App\Filament\Resources\Events\RelationManagers\SessionsRelationManager::class],
                ['Badge templates', 'heroicon-o-identification', \App\Filament\Resources\Events\RelationManagers\BadgeTemplatesRelationManager::class],
                ['Merchandise products', 'heroicon-o-shopping-bag', \App\Filament\Resources\Events\RelationManagers\MerchandiseRelationManager::class],
                ['Attendee merchandise', 'heroicon-o-clipboard-document-list', \App\Filament\Resources\Events\RelationManagers\MerchandiseOrdersRelationManager::class],
                ['Badge types', 'heroicon-o-tag', \App\Filament\Resources\Events\RelationManagers\BadgeTypesRelationManager::class],
            ] as [$label, $icon, $manager]) {
                $key = array_search($manager, $relations, true);
                if ($key !== false && $manager::canViewForRecord($event, EditEvent::class)) {
                    $links[] = [
                        'label' => $label,
                        'icon' => $icon,
                        'url' => EventResource::getUrl('edit', ['record' => $event, 'relation' => $key]),
                        'relation' => (string) $key,
                    ];
                }
            }
        }

        if (\App\Filament\Pages\BadgePrintStation::authorizedBadgeEvents($user)->whereKey($event->id)->exists()
            && ($event->registration_auto_generate_badge || $event->badgeTypes()->exists()
                || $event->badgeTemplates()->exists() || $event->attendees()->whereNotNull('badge_path')->exists())) {
            $links[] = [
                'label' => 'Badge printing',
                'icon' => 'heroicon-o-printer',
                'url' => \App\Filament\Pages\BadgePrintStation::getUrl(['event_id' => $event->id]),
            ];
        }

        $shopConfigured = $event->merchandise_section_enabled
            || \App\Models\MerchandiseProduct::query()->where('event_id', $event->id)->exists()
            || \App\Models\MerchandiseOrder::query()->where('event_id', $event->id)->exists();

        if ($shopConfigured) {
            foreach ([
                ['Shop products', 'heroicon-o-shopping-bag', \App\Filament\Resources\MerchandiseProducts\MerchandiseProductResource::class],
                ['Shop orders', 'heroicon-o-clipboard-document-list', \App\Filament\Resources\MerchandiseOrders\MerchandiseOrderResource::class],
            ] as [$label, $icon, $resource]) {
                if ($resource::canManageMerchandiseFor($event)) {
                    $links[] = [
                        'label' => $label,
                        'icon' => $icon,
                        'url' => $resource::getUrl('index', ['event_id' => $event->id]),
                    ];
                }
            }
        }

        if (EventFinanceWorkspace::canViewFinance($event, $user)) {
            $links[] = [
                'label' => 'Finance overview',
                'icon' => 'heroicon-o-banknotes',
                'url' => EventResource::getUrl('finance', ['record' => $event]),
            ];
        }

        if (EventFinancialAudit::canViewAudit($event, $user)) {
            $links[] = [
                'label' => 'Financial audit',
                'icon' => 'heroicon-o-clipboard-document-list',
                'url' => EventResource::getUrl('financial-audit', ['record' => $event]),
            ];
        }

        if ($event->canViewReportsBy($user) && ! $user->isTicketOrganizer()) {
            foreach ([
                ['Attendee report', \App\Filament\Pages\AttendeeReport::class],
                ['Attendance report', \App\Filament\Pages\AttendanceReport::class],
            ] as [$label, $page]) {
                $links[] = [
                    'label' => $label,
                    'icon' => 'heroicon-o-clipboard-document-list',
                    'url' => $page::getUrl(['event_id' => $event->id]),
                ];
            }
        }

        if ($ticketingConfigured && EventTicketSalesReport::canViewSales($event, $user)) {
            $links[] = [
                'label' => 'Ticket sales report',
                'icon' => 'heroicon-o-ticket',
                'url' => EventResource::getUrl('ticket-sales-report', ['record' => $event]),
            ];
        }

        return $links;
    }

    public function metrics(): array
    {
        $event = $this->event();
        $user = auth()->user();
        $stats = [];

        if ($event->canViewReportsBy($user) || $event->canManageRegistrationBy($user)) {
            $stats[] = ['label' => 'Attendees', 'value' => Attendee::query()->where('event_id', $event->id)->count(), 'icon' => 'heroicon-o-users'];
        }

        if ($event->canViewReportsBy($user) || $event->canBeCheckedInBy($user)) {
            $stats[] = ['label' => 'Checked in', 'value' => CheckIn::query()->where('event_id', $event->id)->distinct('attendee_id')->count('attendee_id'), 'icon' => 'heroicon-o-qr-code'];
        }

        if ($event->canBeManagedBy($user) || ($user->isTicketOrganizer() && $user->assignedTicketingEvents()->where('events.id', $event->id)->exists())) {
            $orders = TicketOrder::query()->where('event_id', $event->id)->where('status', TicketOrder::STATUS_PAID);
            $stats[] = ['label' => 'Paid orders', 'value' => (clone $orders)->count(), 'icon' => 'heroicon-o-shopping-cart'];
            $stats[] = ['label' => 'Tickets sold', 'value' => (clone $orders)->sum('quantity'), 'icon' => 'heroicon-o-ticket'];
        }

        return $stats;
    }
}
