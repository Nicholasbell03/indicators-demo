<?php

namespace App\Enums;

enum IndicatorLevelEnum: string
{
    case ESO = 'eso';
    case PORTFOLIO = 'portfolio';

    public function label(): string
    {
        return match ($this) {
            self::ESO => 'ESO',
            self::PORTFOLIO => 'Portfolio',
        };
    }
}
