<?php

namespace App\Filament\TenantPortfolio\Pages;

use App\Enums\UserPermissions;
use App\Models\Tenant;
use Filament\Pages\Dashboard as BasePage;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BasePage
{
    protected Tenant $tenant;

    protected string $portfolioName = 'Portfolio';

    public function mount(): void
    {
        $this->tenant = app()->bound('currentTenant') ? app('currentTenant') : Tenant::find(config('multitenancy.landlord_id'));
        $portfolio = $this->tenant->portfolio();
        $this->portfolioName = $portfolio ? $portfolio->name : 'Portfolio';
    }

    public function getTitle(): string
    {
        return $this->portfolioName.' Dashboard';
    }

    public function getSubheading(): string
    {
        return 'This area contains data across all tenants in the portfolio';
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : Tenant::find(config('multitenancy.landlord_id'));

        $userCanView = $user && $user->hasPermission(UserPermissions::MANAGE_TENANT_PORTFOLIOS->value);
        $tenantIsPortfolioManager = $tenant && $tenant->isPortfolioManager();

        return $userCanView && $tenantIsPortfolioManager;
    }

    public function getWidgets(): array
    {
        return [
            //
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            //
        ];

    }

    public function getFooterWidgetsColumns(): int|array
    {
        return [
            'md' => 1,
            'xl' => 1,
        ];
    }

    protected function getActions(): array
    {
        return [
            //
        ];
    }
}
