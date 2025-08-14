<?php

namespace App\Providers\Filament;

use Filament\Support\Colors\Color;
use pxlrbt\FilamentEnvironmentIndicator\EnvironmentIndicatorPlugin;

class SharedFilamentPlugins
{
    /**
     * Get the global font configuration for all panels
     */
    public static function getGlobalFont(): string
    {
        return 'Poppins';
    }

    public static function getAdminPlugins(): array
    {
        return [
            self::environmentIndicator(),
        ];
    }

    public static function environmentIndicator(): EnvironmentIndicatorPlugin
    {
        return EnvironmentIndicatorPlugin::make()
            ->color(fn () => match (app()->environment()) {
                'production' => null,
                'staging' => Color::Orange,
                'qa' => Color::Purple,
                default => Color::Blue,
            })
            ->visible(true);
    }
}
