<?php

namespace App\Enums;

enum IndicatorDisplayStatus: string
{
    case NOT_ACHIEVED = 'not_achieved';
    case ACHIEVED = 'achieved';
    case VERIFYING = 'verifying';
    case NOT_SUBMITTED = 'not_submitted';
    case NOT_YET_DUE = 'not_yet_due';
    case NOT_APPLICABLE = 'not_applicable';
    case NEEDS_REVISION = 'needs_revision';

    public function label(): string
    {
        return match ($this) {
            self::NOT_ACHIEVED => 'Not Achieved',
            self::ACHIEVED => 'Achieved',
            self::VERIFYING => 'Verifying',
            self::NOT_SUBMITTED => 'Not Submitted',
            self::NOT_YET_DUE => 'Not Yet Due',
            self::NOT_APPLICABLE => 'N/A',
            self::NEEDS_REVISION => 'Needs Revision',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NOT_ACHIEVED => 'danger',
            self::ACHIEVED => 'success',
            self::VERIFYING => 'info',
            self::NOT_SUBMITTED => 'warning',
            self::NOT_YET_DUE => 'secondary',
            self::NOT_APPLICABLE => 'gray',
            self::NEEDS_REVISION => 'warning',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NOT_ACHIEVED => 'bg-red-500 text-white',
            self::ACHIEVED => 'bg-optt-green-500 text-white',
            self::VERIFYING => 'bg-optt-blue-500 text-white',
            self::NOT_SUBMITTED => 'bg-amber-300 text-gray-800',
            self::NOT_YET_DUE => 'bg-gray-400 text-gray-800',
            self::NOT_APPLICABLE => 'bg-inherit text-gray-800',
            self::NEEDS_REVISION => 'bg-orange-400 text-white',
        };
    }
}
