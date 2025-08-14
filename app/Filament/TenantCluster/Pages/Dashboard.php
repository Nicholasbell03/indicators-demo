<?php

namespace App\Filament\TenantCluster\Pages;

use App\Enums\UserPermissions;
use App\Models\Tenant;
use Filament\Pages\Dashboard as BasePage;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BasePage
{
    protected Tenant $tenant;

    protected string $groupName = 'Group';

    protected static ?string $title = 'Group Dashboard';

    protected ?string $subheading = 'This area contains data across all tenants in the group.';

    public function mount(): void
    {
        $this->tenant = app()->bound('currentTenant') ? app('currentTenant') : Tenant::find(config('multitenancy.landlord_id'));
        $this->groupName = $this->tenant->cluster ? $this->tenant->cluster->name : 'Group';
    }

    public function getTitle(): string
    {
        return $this->groupName.' Dashboard';
    }

    public function getSubheading(): string
    {
        return 'This area contains data across all tenants in the group';
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : Tenant::find(config('multitenancy.landlord_id'));

        $userCanView = $user && $user->hasPermission(UserPermissions::MANAGE_TENANT_GROUPS->value);
        $tenantIsParent = $tenant && $tenant->isParentTenant();

        return $userCanView && $tenantIsParent;
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
