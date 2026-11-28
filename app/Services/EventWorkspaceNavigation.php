<?php

namespace App\Services;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Pages\EventWorkspace;
use App\Models\Event;
use App\Models\User;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;

class EventWorkspaceNavigation
{
    public static function build(): NavigationBuilder|bool
    {
        $user = auth()->user();
        if (! $user instanceof User || $user->is_gate_staff) {
            return true;
        }

        $route = request()->route();
        $name = $route?->getName();

        if (in_array($name, ['filament.admin.resources.events.workspace', 'filament.admin.resources.events.edit', 'filament.admin.resources.events.finance', 'filament.admin.resources.events.financial-audit', 'filament.admin.resources.events.ticket-sales-report', 'filament.admin.resources.events.merchandise-sales-report', 'filament.admin.resources.events.performance-report'], true)) {
            $record = $route->parameter('record');
            $id = $record instanceof Event ? $record->getKey() : $record;
        } elseif (in_array($name, ['filament.admin.pages.attendee-report', 'filament.admin.pages.attendance-report', 'filament.admin.pages.attendance-dashboard', 'filament.admin.pages.check-in-station', 'filament.admin.pages.communication-center', 'filament.admin.pages.badge-print-station', 'filament.admin.resources.attendees.index', 'filament.admin.resources.ticket-orders.index', 'filament.admin.resources.ticket-types.index', 'filament.admin.resources.merchandise-products.index', 'filament.admin.resources.merchandise-orders.index'], true)) {
            $id = request()->query('event_id');
        } else {
            return true;
        }

        if (! is_scalar($id) || ! ctype_digit((string) $id)) {
            return true;
        }

        $event = EventWorkspace::authorizedEvents($user)->whereKey((int) $id)->first();
        if (! $event) {
            return true;
        }

        $builder = new NavigationBuilder();
        $builder->items([
            NavigationItem::make('All events')->icon('heroicon-o-arrow-left')->url(EventResource::getUrl('index')),
            NavigationItem::make('Overview')->icon('heroicon-o-squares-2x2')
                ->url(EventResource::getUrl('workspace', ['record' => $event->id]))
                ->isActiveWhen(fn (): bool => $name === 'filament.admin.resources.events.workspace'),
        ]);

        $otherEvents = EventWorkspace::authorizedEvents($user)
            ->where('events.id', '!=', $event->id)
            ->orderByDesc('starts_at')->orderByDesc('events.id')
            ->limit(8)->get(['events.id', 'events.name']);

        if ($otherEvents->isNotEmpty()) {
            $builder->group(\Filament\Navigation\NavigationGroup::make('Switch event')
                ->items($otherEvents->map(
                    fn (Event $other): NavigationItem => NavigationItem::make($other->name)
                        ->icon('heroicon-o-calendar')
                        ->url(EventResource::getUrl('workspace', ['record' => $other->id]))
                        ->isActiveWhen(fn (): bool => false)
                )->all())->collapsed());
        }

        $links = collect(EventWorkspace::linksFor($event))->keyBy('label');
        foreach ([
            'Event management' => ['Registration', 'Attendees', 'Ticket types', 'Ticket orders', 'Programme'],
            'Operations' => ['Check-in station', 'Badge templates', 'Badge types', 'Badge printing', 'Communications'],
            'Merchandise' => ['Merchandise products', 'Attendee merchandise', 'Shop products', 'Shop orders'],
            'Finance' => ['Finance overview', 'Financial audit'],
            'Reports' => ['Event performance', 'Attendee report', 'Attendance dashboard', 'Attendance report', 'Ticket sales report', 'Merchandise sales report'],
            'Configuration' => ['Event settings'],
        ] as $heading => $labels) {
            $items = [];
            foreach ($labels as $label) {
                if ($link = $links->get($label)) {
                    $items[] = NavigationItem::make($label)->icon($link['icon'])->url($link['url'])
                        ->isActiveWhen(fn (): bool => request()->url() === strtok($link['url'], '?')
                            && ($name !== 'filament.admin.resources.events.edit'
                                || (string) request()->query('relation', '') === ($link['relation'] ?? '')));
                }
            }
            if ($items !== []) {
                $builder->group($heading, $items);
            }
        }

        return $builder;
    }
}
