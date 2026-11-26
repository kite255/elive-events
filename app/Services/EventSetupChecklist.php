<?php

namespace App\Services;

use App\Models\Event;

class EventSetupChecklist
{
    public function items(Event $event): array
    {
        $event->loadMissing(['ticketSetting', 'paymentSetting']);
        $registration = (bool) $event->registration_is_open;
        $tickets = (bool) $event->ticketSetting?->ticket_sales_enabled;
        $paid = $registration && $event->paymentsAreEnabled();

        $items = [
            $this->item('Event information', filled($event->name) && filled($event->event_type) && filled($event->organization_id), 'Confirm name, type and organization.'),
            $this->item('Schedule', $event->starts_at && $event->ends_at && $event->ends_at->gte($event->starts_at), 'Set the start and end date and time.'),
            $this->item('Public page and branding', filled($event->registration_banner_image_path), 'Optional: add an event cover image.', true),
            $this->item('Admission', null, $registration && $tickets ? 'Registration and ticket sales selected.' : ($registration ? 'Public registration selected.' : ($tickets ? 'Ticket sales selected.' : 'Public admission disabled; confirm manual management is intended.'))),
        ];

        if ($registration) {
            $items[] = $this->item('Registration fields', null, 'Review attendee fields, access code, approvals and capacity.');
            if ($paid) {
                $items[] = $this->item('Registration fee', (float) $event->registrationFee() > 0, 'Set a positive fee and verify the payment gateway separately.');
            }
            if ($event->registration_auto_generate_badge) {
                $items[] = $this->item('Badge design', $event->badgeTemplates()->exists(), 'Optional: review badge template or confirm the default design.', true);
            }
            $items[] = $this->item('Registration communication', null, 'Review enabled channels and test delivery with a controlled recipient.');
        }

        if ($tickets) {
            $items[] = $this->item('Ticket types', $event->publicTicketTypes()->exists(), 'Create at least one active public ticket type; verify pricing and checkout.');
        }

        $items[] = $this->item('Event team and check-in', null, 'Review staff permissions and test the entry workflow.');
        $items[] = $this->item('Publication', null, 'Review all settings before changing the event status to Active.');

        return $items;
    }

    private function item(string $label, ?bool $complete, string $detail, bool $optional = false): array
    {
        return compact('label', 'complete', 'detail', 'optional');
    }
}
