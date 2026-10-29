<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')

            /*
            |--------------------------------------------------------------------------
            | Authentication
            |--------------------------------------------------------------------------
            */

            ->login(Login::class)

            /*
            |--------------------------------------------------------------------------
            | eLive Branding
            |--------------------------------------------------------------------------
            */

            ->brandName('eLive Events')
            ->brandLogo(asset('eLive-Logo.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.ico'))

            /*
            |--------------------------------------------------------------------------
            | Font
            |--------------------------------------------------------------------------
            |
            | Official eLive typography:
            | Creato Display
            |
            */

            ->font(
                'Creato Display',
                url: asset('css/creato-font.css'),
                provider: LocalFontProvider::class,
            )

            /*
            |--------------------------------------------------------------------------
            | Filament Admin Theme
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            | Do not load resources/css/app.css directly as the Filament theme.
            |
            | This dedicated stylesheet imports Filament's own base theme first,
            | then applies eLive-specific customizations.
            |
            */

            ->viteTheme(
                'resources/css/filament/admin/theme.css'
            )

            /*
            |--------------------------------------------------------------------------
            | Official eLive Brand Colors
            |--------------------------------------------------------------------------
            |
            | Deep Navy Blue : #161943
            | Light Blue     : #007AB2
            | Orange Peel    : #FF9800
            |
            */

            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::hex('#161943'),
                'info' => Color::hex('#007AB2'),
                'warning' => Color::hex('#FF9800'),

                'gray' => Color::Slate,
                'success' => Color::Green,
                'danger' => Color::Red,
            ])

            /*
            |--------------------------------------------------------------------------
            | Resources
            |--------------------------------------------------------------------------
            */

            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\\Filament\\Resources'
            )

            /*
            |--------------------------------------------------------------------------
            | Pages
            |--------------------------------------------------------------------------
            */

            ->discoverPages(
                in: app_path('Filament/Pages'),
                for: 'App\\Filament\\Pages'
            )

            ->navigationGroups([
                'Events & Registration',
                'Ticketing',
                'Check-in & Attendance',
                'Merchandise',
                'Badges',
                'Finance',
                'Donations',
                'Communications',
                'Reports',
                'Administration',
            ])

            ->pages([
                Dashboard::class,
            ])

            /*
            |--------------------------------------------------------------------------
            | Widgets
            |--------------------------------------------------------------------------
            */

            ->discoverWidgets(
                in: app_path('Filament/Widgets'),
                for: 'App\\Filament\\Widgets'
            )

            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])

            /*
            |--------------------------------------------------------------------------
            | Middleware
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Authentication Middleware
            |--------------------------------------------------------------------------
            */

            ->authMiddleware([
                Authenticate::class,
            ]);

        // Check-in pages use SPA navigation in staging so that operators can
        // test whether full-page reloads cause the visible sidebar flash.
        // Production retains the existing scanner exclusions until camera
        // lifecycle and check-in behavior have been verified.
        if (app()->environment(['staging', 'production'])) {
            $exceptions = [
                '*/admin/badge-print-station*',
                '*/admin/*/design*',
                '*/admin/*/designer*',
            ];

            if (app()->environment('production')) {
                $exceptions = array_merge($exceptions, [
                    '*/admin/check-in-station*',
                    '*/admin/gate-check-in*',
                    '*/admin/manual-check-in*',
                    '*/admin/ticket-scanner*',
                ]);
            }

            $panel->spa()->spaUrlExceptions($exceptions);
        }

        return $panel;
    }
}