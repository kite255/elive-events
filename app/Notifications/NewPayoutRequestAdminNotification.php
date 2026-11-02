<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewPayoutRequestAdminNotification extends Notification
{
    public function __construct(
        public readonly string $reference,
        public readonly string $eventName,
        public readonly string $amount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Organizer Payout Request - '.$this->reference)
            ->greeting('New organizer payout request')
            ->line('Event: '.$this->eventName)
            ->line('Reference: '.$this->reference)
            ->line('Requested amount: '.$this->amount)
            ->line('Review this request in the eLive Events admin Payout Requests page.')
            ->line('Approval requires independently verified settlement; this alert does not authorize a transfer.');
    }
}
