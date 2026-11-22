<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use SensitiveParameter;

trait ChecksPanelPasswordRecoveryCredentials
{
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $credentials = parent::getCredentialsFromFormData($data);
        $panel = filament()->getCurrentOrDefaultPanel();

        $credentials[] = static function (Builder $query) use ($panel): void {
            $user = (clone $query)->first();

            $query->whereKey($user instanceof User && $user->canAccessPanel($panel)
                ? $user->getKey()
                : null);
        };

        return $credentials;
    }
}
