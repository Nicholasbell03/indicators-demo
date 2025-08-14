<?php

namespace App\Policies;

use App\Enums\UserPermissions;
use App\Models\IndicatorSuccess;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class IndicatorSuccessPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {        
        $tenant = app('currentTenant');

        return ($tenant->isLandlord() || $tenant->isClusterLandlord()) && ($user->isAdmin() || $user->hasPermission(UserPermissions::MANAGE_INDICATOR_SUCCESS->value));
    }

    /**1
     * Determine whether the user can view the model.
     */
    public function view(User $user, IndicatorSuccess $indicatorSuccess): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(UserPermissions::MANAGE_INDICATOR_SUCCESS->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, IndicatorSuccess $indicatorSuccess): bool
    {
        // TODO: Implement additional checks here
        return $user->hasPermission(UserPermissions::MANAGE_INDICATOR_SUCCESS->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, IndicatorSuccess $indicatorSuccess): bool
    {
        // TODO: Implement additional checks here
        return $user->hasPermission(UserPermissions::MANAGE_INDICATOR_SUCCESS->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, IndicatorSuccess $indicatorSuccess): bool
    {
        return $user->hasPermission(UserPermissions::MANAGE_INDICATOR_SUCCESS->value);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, IndicatorSuccess $indicatorSuccess): bool
    {
        Log::error('Force delete indicator success not implemented');

        return false;
    }
}
