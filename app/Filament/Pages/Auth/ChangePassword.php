<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ChangePassword extends EditProfile
{
    public function getTitle(): string
    {
        return 'Change password';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getCurrentPasswordFormComponent()->visible(),
            $this->getPasswordFormComponent()->required(),
            $this->getPasswordConfirmationFormComponent()->visible(),
        ]);
    }

    protected function beforeSave(): void
    {
        $this->getUser()->setRememberToken(Str::random(60));
    }

    protected function afterSave(): void
    {
        Password::broker(filament()->getAuthPasswordBroker())->deleteToken($this->getUser());
        $this->data['currentPassword'] = null;
    }
}
