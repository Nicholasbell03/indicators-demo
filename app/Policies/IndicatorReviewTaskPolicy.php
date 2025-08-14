<?php

namespace App\Policies;

use App\Enums\UserPermissions;
use App\Models\IndicatorReviewTask;
use App\Models\User;

class IndicatorReviewTaskPolicy
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
    public function view(User $user, IndicatorReviewTask $indicatorReviewTask): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Indicator review tasks cannot be created by a user.
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, IndicatorReviewTask $indicatorReviewTask): bool
    {
        return $user->hasPermission(UserPermissions::MANAGE_INDICATOR_REVIEW_TASKS->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, IndicatorReviewTask $indicatorReviewTask): bool
    {
        return false;
    }
}
