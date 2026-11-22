<?php

namespace App\Providers\Filament;

use App\Filament\Gate\GateTicketScanner;
use App\Filament\Pages\Auth\ChangePassword;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Auth\ResetPassword;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class GatePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('gate')
            ->path('gate')
            ->authGuard('gate')
            ->login(Login::class)
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            ->profile(ChangePassword::class)
            ->brandName('eLive Events')
            ->brandLogo(asset('eLive-Logo.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.ico'))
            ->font(
                'Creato Display',
                url: asset('css/creato-font.css'),
                provider: LocalFontProvider::class,
            )
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::hex('#161943'),
                'gray' => Color::Slate,
            ])
            ->navigation(false)
            ->pages([GateTicketScanner::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class], isPersistent: true);
    }
}
