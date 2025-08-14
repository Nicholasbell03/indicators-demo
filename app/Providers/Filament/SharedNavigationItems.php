<?php

namespace App\Providers\Filament;

use App\Enums\UserPermissions;
use App\Models\Tenant;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Auth;

class SharedNavigationItems
{
    public static function getAppItem(): NavigationItem
    {
        return NavigationItem::make('app')
            ->label('Back to app')
            ->url('/')
            ->icon('heroicon-o-arrow-left');
    }

    public static function getCreatorItem(): NavigationItem
    {
        return NavigationItem::make('creator')
            ->label('Creator dashboard')
            ->url(fn (): string => route('filament.creator.pages.dashboard'))
            ->icon('heroicon-o-arrow-left')
            ->visible(function (): bool {
                $tenant = app()->bound('currentTenant') ? app('currentTenant') : Tenant::find(config('multitenancy.landlord_id'));

                /** @var \App\Models\User $user */
                $user = Auth::user();

                return $user && $user->isAdmin() && $tenant->isLandlord();
            });
    }

    public static function getAdminItem(): NavigationItem
    {
        return NavigationItem::make('admin')
            ->label('Admin dashboard')
            ->url(fn (): string => route('filament.admin.pages.dashboard'))
            ->icon('heroicon-o-arrow-left')
            ->visible(function (): bool {
                /** @var \App\Models\User $user */
                $user = Auth::user();

                return $user && ($user->hasPermission(UserPermissions::VIEW_ADMIN_DASHBOARD->value) || $user->isGuide());
            });
    }

    public static function getEnvoyItem(): NavigationItem
    {
        return NavigationItem::make('envoy')
            ->label('Envoy dashboard')
            ->url(fn (): string => route('filament.app.pages.dashboard'))
            ->icon('heroicon-o-arrow-left')
            ->visible(function (): bool {
                $tenant = app()->bound('currentTenant') ? app('currentTenant') : Tenant::find(config('multitenancy.landlord_id'));

                /** @var \App\Models\User $user */
                $user = Auth::user();

                return $user && $user->isAdmin() && $tenant->isLandlord();
            });
    }

    public static function getTenantGroupItem(): NavigationItem
    {
        return NavigationItem::make('tenant_cluster')
            ->label('Parent Group Dashboard')
            ->url(fn (): string => route('filament.tenantCluster.pages.dashboard'))
            ->icon('heroicon-o-arrow-left')
            ->visible(function (): bool {
                /** @var \App\Models\User $user */
                $user = Auth::user();
                $tenant = app()->bound('currentTenant') ? app('currentTenant') : Tenant::find(config('multitenancy.landlord_id'));

                $userCanView = $user && $user->hasPermission(UserPermissions::MANAGE_TENANT_GROUPS->value);

                return $userCanView && $tenant->isParentTenant();
            });
    }

    public static function getTenantPortfolioItem(): NavigationItem
    {
        return NavigationItem::make('tenant_portfolio')
            ->label('Tenant Portfolio Dashboard')
            ->url(fn (): string => route('filament.tenantPortfolio.pages.dashboard'))
            ->icon('heroicon-o-arrow-left')
            ->visible(function (): bool {
                /** @var \App\Models\User $user */
                $user = Auth::user();
                $tenant = app()->bound('currentTenant') ? app('currentTenant') : Tenant::find(config('multitenancy.landlord_id'));

                $userCanView = $user && $user->hasPermission(UserPermissions::MANAGE_TENANT_PORTFOLIOS->value);

                return $userCanView && $tenant->isPortfolioManager();
            });
    }

    public static function getSupportItem(): NavigationItem
    {
        return NavigationItem::make('email_support')
            ->label('Email support')
            ->url(function () {
                $tenant = app()->bound('currentTenant') ? app('currentTenant') : Tenant::find(config('multitenancy.landlord_id'));

                return 'mailto:'.($tenant?->support_email ?? 'help@flowcodesupport.on.spiceworks.com');
            })
            ->icon('heroicon-o-lifebuoy')
            ->group('Support');
    }
}
