<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\LocalSvgAvatarProvider;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Livewire\Filament\DatabaseNotifications;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Hammadzafar05\MobileBottomNav\MobileBottomNav;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use JibayMcs\FilamentTour\FilamentTourPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->defaultAvatarProvider(LocalSvgAvatarProvider::class)
            ->id('admin')
            ->path('admin')
            ->homeUrl('/admin')
            ->login(Login::class)
            ->profile(EditProfile::class)
            ->colors([
                'danger' => Color::Rose,
                'primary' => Color::Blue,
            ])
            ->font('Arial')
            ->databaseNotifications(
                condition: fn (): bool => auth()->check(),
                livewireComponent: DatabaseNotifications::class,
            )
            ->databaseNotificationsPolling(null)
            ->brandName(str('<div style="align-items: center; display: flex;"><img src="'.asset('logo_light.png').'" alt="DocuCast" style="height: 40px; margin-right: 10px;"> DocuCast</div>')->inlineMarkdown()->toHtmlString())
            ->favicon(asset('logo_light.png'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
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
            ->plugins([
                FilamentShieldPlugin::make(),
                MobileBottomNav::make()
                    ->fromNavigation(limit: 4),
                FilamentTourPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
