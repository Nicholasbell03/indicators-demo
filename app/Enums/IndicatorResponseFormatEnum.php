<?php

namespace App\Enums;

enum IndicatorResponseFormatEnum: string
{
    case NUMERIC = 'numeric';
    case PERCENTAGE = 'percentage';
    case MONETARY = 'monetary';
    case BOOLEAN = 'boolean';

    public function label(): string
    {
        return match ($this) {
            self::NUMERIC => 'Numeric',
            self::PERCENTAGE => 'Percentage',
            self::MONETARY => 'Monetary Value',
            self::BOOLEAN => 'Yes/No',
        };
    }
}
