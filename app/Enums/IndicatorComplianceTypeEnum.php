<?php

namespace App\Enums;

enum IndicatorComplianceTypeEnum: string
{
    case ELEMENT_PROGRESS = 'element-progress';
    case ATTENDANCE_LEARNING = 'attendance-learning';
    case ATTENDANCE_MENTORING = 'attendance-mentoring';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ELEMENT_PROGRESS => 'Element Progress',
            self::ATTENDANCE_LEARNING => 'Attendance Learning',
            self::ATTENDANCE_MENTORING => 'Attendance Mentoring',
            self::OTHER => 'Other',
        };
    }
}
