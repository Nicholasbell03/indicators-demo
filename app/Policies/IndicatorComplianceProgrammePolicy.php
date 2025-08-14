<?php

namespace App\Policies;

use App\Enums\UserPermissions;
use App\Models\IndicatorComplianceProgramme;
use App\Models\User;

class IndicatorComplianceProgrammePolicy
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
    public function view(User $user, IndicatorComplianceProgramme $indicatorComplianceProgramme): bool
    {
        return true;
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
    public function update(User $user, IndicatorComplianceProgramme $indicatorComplianceProgramme): bool
    {
        return $user->hasPermission(UserPermissions::MANAGE_INDICATOR_COMPLIANCE->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, IndicatorComplianceProgramme $indicatorComplianceProgramme): bool
    {
        return $user->hasPermission(UserPermissions::MANAGE_INDICATOR_COMPLIANCE->value);
    }
}
