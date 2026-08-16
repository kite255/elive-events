<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Support\Enums\Width;

class Login extends BaseLogin
{
    /*
    |--------------------------------------------------------------------------
    | Custom Login View
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | In your installed Filament version, SimplePage::$view is NON-STATIC.
    | Therefore this property must also be non-static.
    |
    */

    protected string $view = 'filament.pages.auth.login';

    /*
    |--------------------------------------------------------------------------
    | Login Page Width
    |--------------------------------------------------------------------------
    */

    public function getMaxContentWidth(): Width
    {
        return Width::FiveExtraLarge;
    }

    /*
    |--------------------------------------------------------------------------
    | Body Class
    |--------------------------------------------------------------------------
    |
    | Used by resources/views/filament/pages/auth/login.blade.php
    | to scope the custom eLive login styles.
    |
    */

    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'elive-login-page',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Email Field
    |--------------------------------------------------------------------------
    */

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email address')
            ->placeholder('Enter your email address')
            ->email()
            ->required()
            ->autocomplete('email')
            ->autofocus();
    }

    /*
    |--------------------------------------------------------------------------
    | Password Field
    |--------------------------------------------------------------------------
    */

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Password')
            ->placeholder('Enter your password')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required();
    }

    /*
    |--------------------------------------------------------------------------
    | Remember Me
    |--------------------------------------------------------------------------
    */

    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label('Remember me');
    }
}
