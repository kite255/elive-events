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

        if (in_array($name, ['filament.admin.resources.events.workspace', 'filament.admin.resources.events.edit'], true)) {
            $record = $route->parameter('record');
            $id = $record instanceof Event ? $record->getKey() : $record;
        } elseif (in_array($name, ['filament.admin.pages.check-in-station', 'filament.admin.pages.communication-center', 'filament.admin.pages.badge-print-station', 'filament.admin.resources.attendees.index', 'filament.admin.resources.ticket-orders.index', 'filament.admin.resources.ticket-types.index'], true)) {
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

        $links = collect(EventWorkspace::linksFor($event))->keyBy('label');
        foreach ([
            'Event management' => ['Registration', 'Attendees', 'Ticket types', 'Ticket orders', 'Programme'],
            'Operations' => ['Check-in station', 'Badge templates', 'Badge types', 'Badge printing', 'Communications'],
            'Merchandise' => ['Merchandise products', 'Attendee merchandise'],
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
