<?php

namespace App\Enums;

/**
 * Defines the possible statuses for an indicator submission.
 */
enum IndicatorSubmissionStatusEnum: string
{
    case PENDING_VERIFICATION_1 = 'pending_verification_1';
    case PENDING_VERIFICATION_2 = 'pending_verification_2';
    case REJECTED = 'rejected';
    case APPROVED = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_VERIFICATION_1 => 'Pending Verification (1)',
            self::PENDING_VERIFICATION_2 => 'Pending Verification (2)',
            self::REJECTED => 'Rejected',
            self::APPROVED => 'Approved',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING_VERIFICATION_1 => 'warning',
            self::PENDING_VERIFICATION_2 => 'warning',
            self::REJECTED => 'danger',
            self::APPROVED => 'success',
        };
    }
}
