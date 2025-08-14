<?php

namespace App\Policies;

use App\Models\IndicatorSubmissionReview;
use App\Models\User;

class IndicatorSubmissionReviewPolicy
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
    public function view(User $user, IndicatorSubmissionReview $indicatorSubmissionReview): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, IndicatorSubmissionReview $indicatorSubmissionReview): bool
    {
        // Reviews are immutable.
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, IndicatorSubmissionReview $indicatorSubmissionReview): bool
    {
        // Reviews are immutable.
        return false;
    }
}
