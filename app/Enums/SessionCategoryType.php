<?php

namespace App\Enums;

enum SessionCategoryType: string
{
    case LEARNING = 'learning';
    case MENTORING = 'mentoring';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::LEARNING => 'Learning',
            self::MENTORING => 'Mentoring',
            self::OTHER => 'Other',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::LEARNING => 'Learning sessions',
            self::MENTORING => 'Mentoring sessions',
            self::OTHER => 'Other sessions',
        };
    }

    public function getCorrespondingIndicatorComplianceType(): IndicatorComplianceTypeEnum
    {
        return match ($this) {
            self::LEARNING => IndicatorComplianceTypeEnum::ATTENDANCE_LEARNING,
            self::MENTORING => IndicatorComplianceTypeEnum::ATTENDANCE_MENTORING,
            default => throw new \Exception('No corresponding indicator compliance type found for session category type'),
        };
    }
}
