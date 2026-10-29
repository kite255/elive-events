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
        public readonly ?string $amount = null,
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
        $subject = match ($this->status) {
            'approved' => 'Payout Request Approved - '.$this->reference,
            'rejected' => 'Payout Request Rejected - '.$this->reference,
            'paid' => 'Payout Payment Confirmed - '.$this->reference,
            default => 'Payout Request Received - '.$this->reference,
        };

        $amountLine = $this->amount !== null
            ? "\nAmount: ".$this->amount
            : '';

        $message = "Hello ".$notifiable->name.",\n\n"
            ."Your payout request has been ".($this->status === 'paid' ? 'marked as paid' : $this->status).".\n\n"
            ."Event: ".$this->eventName."\n"
            ."Request Reference: ".$this->reference
            .$amountLine."\n\n"
            .($this->status === 'approved'
                ? "Your request is approved but payment has not yet been transferred."
                : ($this->status === 'paid'
                    ? "The platform has recorded the payout as paid. Contact the administrator if you have not received the funds."
                    : "For questions, contact the eLive Events platform administrator."));

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.elive', [
                'subject' => $subject,
                'messageBody' => $message,
                'attendee' => null,
                'event' => null,
                'emailLabel' => 'Payout Request '.ucfirst($this->status),
                'alertTitle' => null,
                'alertMessage' => null,
                'actionUrl' => null,
                'actionLabel' => null,
                'actionIntro' => null,
                'actionNote' => null,
            ]);
    }
}
