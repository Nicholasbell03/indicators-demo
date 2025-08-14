<?php

namespace App\Policies;

use App\Models\IndicatorSubmission;
use App\Models\User;

class IndicatorSubmissionPolicy
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
    public function view(User $user, IndicatorSubmission $indicatorSubmission): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Nobody is restricted from creating submissions
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, IndicatorSubmission $indicatorSubmission): bool
    {
        // Currently each submission is immutable.
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, IndicatorSubmission $indicatorSubmission): bool
    {
        // Currently each submission is immutable.
        return false;
    }
}
