<?php

namespace App\Providers\Filament;

use App\Helpers\TenantColourRegistrar;
use App\Http\Controllers\Auth\AuthController;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class TenantPortfolioPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('tenantPortfolio')
            ->path('/admin/tenant-portfolio')
            ->font(SharedFilamentPlugins::getGlobalFont())
            ->spa()
            ->spaUrlExceptions(fn (): array => [
                '/',
            ])
            ->sidebarCollapsibleOnDesktop()
            ->brandName('Tenant portfolio')
            ->brandLogo(function (): string {
                $tenant = app()->bound('currentTenant') ? app('currentTenant') : Tenant::find(config('multitenancy.landlord_id'));
                if ($tenant && $tenant->menu_logo) {
                    return $tenant->menu_logo;
                }

                return asset('images/login_logo.png');
            })
            ->login([AuthController::class, 'login'])
            ->favicon('/images/favicon.ico')
            ->bootUsing(function () {
                Filament::serving(function () {
                    TenantColourRegistrar::registerWithFallback();
                });
            })
            ->discoverResources(in: app_path('Filament/TenantPortfolio/Resources'), for: 'App\\Filament\\TenantPortfolio\\Resources')
            ->discoverPages(in: app_path('Filament/TenantPortfolio/Pages'), for: 'App\\Filament\\TenantPortfolio\\Pages')
            ->pages([
                //
            ])
            ->discoverWidgets(in: app_path('Filament/TenantPortfolio/Widgets'), for: 'App\\Filament\\TenantPortfolio\\Widgets')
            ->widgets([
                //
            ])
            ->navigationItems(
                [
                    SharedNavigationItems::getAppItem(),
                    SharedNavigationItems::getAdminItem(),
                    SharedNavigationItems::getTenantGroupItem(),
                ]
            )
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
            ->plugins(SharedFilamentPlugins::getAdminPlugins());
    }
}
