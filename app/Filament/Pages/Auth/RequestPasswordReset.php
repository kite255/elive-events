<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    use ChecksPanelPasswordRecoveryCredentials;

    public function request(): void
    {
        // Sign emailed links for the configured origin regardless of proxy headers.
        URL::useOrigin(config('app.url'));

        try {
            parent::request();
        } finally {
            URL::useOrigin(null);
        }
    }

    protected function getSentNotification(string $status): ?Notification
    {
        return Notification::make()
            ->title('If your account can sign in here, we’ll email you a reset link.')
            ->success();
    }

    protected function getFailureNotification(string $status): ?Notification
    {
        $this->form->fill();

        return $this->getSentNotification($status);
    }
}
