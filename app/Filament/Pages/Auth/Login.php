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
    | eLive Events Custom Login View
    |--------------------------------------------------------------------------
    |
    | The visual branding for this page is handled by:
    |
    | resources/views/filament/pages/auth/login.blade.php
    | public/css/creato-font.css
    | resources/css/app.css
    |
    | IMPORTANT:
    | In the installed Filament version, SimplePage::$view is non-static.
    | Keep this property non-static.
    |
    */

    protected string $view = 'filament.pages.auth.login';

    /*
    |--------------------------------------------------------------------------
    | Login Page Width
    |--------------------------------------------------------------------------
    |
    | Provides enough room for the custom eLive split/login layout while
    | remaining responsive on smaller screens.
    |
    */

    public function getMaxContentWidth(): Width
    {
        return Width::FiveExtraLarge;
    }

    /*
    |--------------------------------------------------------------------------
    | Body Attributes
    |--------------------------------------------------------------------------
    |
    | Used to scope the custom eLive login styles without affecting other
    | Filament pages.
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
