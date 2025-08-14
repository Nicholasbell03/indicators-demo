<?php

namespace App\Policies;

use App\Models\IndicatorTask;
use App\Models\User;

/**
 * TODO: Implement this policy.
 */
class IndicatorTaskPolicy
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
    public function view(User $user, IndicatorTask $indicatorTask): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Indicator tasks cannot be created by a user.
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, IndicatorTask $indicatorTask): bool
    {
        // TODO: Make this permissions based
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, IndicatorTask $indicatorTask): bool
    {
        // Indicator tasks cannot be deleted by a user
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, IndicatorTask $indicatorTask): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, IndicatorTask $indicatorTask): bool
    {
        return false;
    }
}
