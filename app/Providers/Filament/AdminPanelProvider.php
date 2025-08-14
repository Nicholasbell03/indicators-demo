<?php

namespace App\Providers\Filament;

use App\Helpers\TenantColourRegistrar;
use App\Http\Controllers\Auth\AuthController;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('/admin')
            ->brandName('App management')
            ->font(SharedFilamentPlugins::getGlobalFont())
            ->spa()
            ->spaUrlExceptions(fn (): array => [
                '/',
            ])
            ->sidebarCollapsibleOnDesktop()
            ->brandLogo(function (): string {
                $tenant = app('currentTenant');
                if ($tenant && $tenant->menu_logo) {
                    return $tenant->login_logo;
                }

                return asset('images/login_logo.png');
            })
            ->login([AuthController::class, 'login'])
            ->favicon('/images/favicon.ico')
            ->bootUsing(function () {
                Filament::serving(function () {
                    TenantColourRegistrar::register();
                });
            })
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
            ])
            // ->pages([
            //     \App\Filament\Admin\Pages\Dashboard::class,
            //     \App\Filament\Admin\Pages\MentorDashboard::class,
            // ])
            ->discoverClusters(in: app_path('Filament/Admin/Clusters'), for: 'App\\Filament\\Admin\\Clusters')
            ->pages([
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->navigationItems(
                [
                    SharedNavigationItems::getAppItem(),
                    SharedNavigationItems::getCreatorItem(),
                    SharedNavigationItems::getEnvoyItem(),
                    SharedNavigationItems::getTenantGroupItem(),
                    SharedNavigationItems::getTenantPortfolioItem(),
                    SharedNavigationItems::getSupportItem(),
                ],
            )
            ->navigationGroups([
                'Engagements',
                'Manage',
                'Support',
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
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                SharedFilamentPlugins::environmentIndicator(),
                FilamentFullCalendarPlugin::make()->selectable(),
            ]);
    }
}
