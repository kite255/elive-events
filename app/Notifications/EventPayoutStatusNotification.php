<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventPayoutStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $reference,
        public readonly string $eventName,
        public readonly string $status,
    ) {}

    public function via(object $notifiable): array
    {
        return filled($notifiable->email ?? null) ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'format' => 'filament',
            'title' => 'Payout request '.ucfirst($this->status),
            'body' => 'Request '.$this->reference.' for '.$this->eventName.' is now '.$this->status.'.',
            'status' => $this->status,
            'duration' => 'persistent',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('eLive Events payout update: '.$this->reference)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your payout request '.$this->reference.' for '.$this->eventName.' is now '.$this->status.'.')
            ->line('For payment questions, contact the eLive Events platform administrator.')
            ->line('This notification does not contain bank or mobile-money account information.');
    }
}
