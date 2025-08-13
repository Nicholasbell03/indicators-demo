<?php

namespace App\Enums;

enum IndicatorTaskStatusEnum: string
{
    case PENDING = 'pending';
    case SUBMITTED = 'submitted';
    case COMPLETED = 'completed';
    case NEEDS_REVISION = 'needs_revision';
    case OVERDUE = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SUBMITTED => 'In Verification',
            self::COMPLETED => 'Complete',
            self::NEEDS_REVISION => 'Needs Revision',
            self::OVERDUE => 'Overdue',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::SUBMITTED => 'info',
            self::COMPLETED => 'success',
            self::NEEDS_REVISION => 'danger',
            self::OVERDUE => 'danger',
        };
    }

    public static function pendingTypes(): array
    {
        return [
            self::PENDING,
            self::NEEDS_REVISION,
            self::OVERDUE,
        ];
    }

    public static function submittableTypes(): array
    {
        return [
            self::PENDING,
            self::NEEDS_REVISION,
            self::OVERDUE,
        ];
    }

    public static function viewableTypes(): array
    {
        return [
            self::COMPLETED,
            self::SUBMITTED,
        ];
    }

    /**
     * Get all statuses that can be used in the database.
     * Only overdue is computed on demand and not stored in the database.
     */
    public static function databaseTypes(): array
    {
        return array_filter(self::cases(), fn ($case) => $case !== self::OVERDUE);
    }
}
