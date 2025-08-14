<?php

namespace App\Policies;

use App\Enums\UserPermissions;
use App\Models\IndicatorCompliance;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class IndicatorCompliancePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        return $tenant
            && ($tenant->isLandlord() || $tenant->isClusterLandlord())
            && ($user->isAdmin() || $user->hasPermission(UserPermissions::MANAGE_INDICATOR_COMPLIANCE->value));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, IndicatorCompliance $indicatorCompliance): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(UserPermissions::MANAGE_INDICATOR_COMPLIANCE->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, IndicatorCompliance $indicatorCompliance): bool
    {
        // TODO: Implement additional checks here
        return $user->hasPermission(UserPermissions::MANAGE_INDICATOR_COMPLIANCE->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, IndicatorCompliance $indicatorCompliance): bool
    {
        // TODO: Implement additional checks here
        return $user->hasPermission(UserPermissions::MANAGE_INDICATOR_COMPLIANCE->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, IndicatorCompliance $indicatorCompliance): bool
    {
        return $user->hasPermission(UserPermissions::MANAGE_INDICATOR_COMPLIANCE->value);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, IndicatorCompliance $indicatorCompliance): bool
    {
        Log::error('Force delete indicator compliance not implemented');

        return false;
    }
}
