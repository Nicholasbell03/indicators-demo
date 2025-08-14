<?php

namespace App\Policies;

use App\Models\IndicatorSubmissionAttachment;
use App\Models\User;

class IndicatorSubmissionAttachmentPolicy
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
    public function view(User $user, IndicatorSubmissionAttachment $indicatorSubmissionAttachment): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Nobody is restricted from creating attachments
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, IndicatorSubmissionAttachment $indicatorSubmissionAttachment): bool
    {
        // As per submissions, nobody can update attachments
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, IndicatorSubmissionAttachment $indicatorSubmissionAttachment): bool
    {
        // As per submissions, nobody can delete attachments
        return false;
    }
}
