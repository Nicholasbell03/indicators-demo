<?php

namespace App\Policies;

use App\Enums\UserPermissions;
use App\Models\IndicatorSuccessProgrammeMonth;
use App\Models\User;

class IndicatorSuccessProgrammeMonthPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, IndicatorSuccessProgrammeMonth $indicatorSuccessProgrammeMonth): bool
    {
        return true;
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
    public function update(User $user, IndicatorSuccessProgrammeMonth $indicatorSuccessProgrammeMonth): bool
    {
        return $user->hasPermission(UserPermissions::MANAGE_INDICATOR_SUCCESS->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, IndicatorSuccessProgrammeMonth $indicatorSuccessProgrammeMonth): bool
    {
        return $user->hasPermission(UserPermissions::MANAGE_INDICATOR_SUCCESS->value);
    }
}
