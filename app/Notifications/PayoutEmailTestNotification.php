<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Explicitly labelled connectivity check; not a real finance transaction. */
class PayoutEmailTestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[TEST] eLive Events payout alert delivery')
            ->greeting('Payout alert email test')
            ->line('This is a test message from eLive Events System Email Settings.')
            ->line('No payout request was created, approved or paid.')
            ->line('If you received this message, email delivery is functioning for this recipient.');
    }
}
