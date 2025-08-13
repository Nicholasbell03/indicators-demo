<?php

namespace App\Enums;

enum IndicatorProgrammeStatusEnum: string
{
    case PENDING = 'pending';
    case PUBLISHED = 'published';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PUBLISHED => 'Published',
        };
    }
}
