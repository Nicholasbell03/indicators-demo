<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Exception thrown when performance thresholds are exceeded or service unavailable
 */
class IndicatorServicePerformanceException extends IndicatorServiceException
{
    public static function cacheUnavailable(string $operation): self
    {
        return new self(
            "Cache service unavailable for operation: {$operation}",
            503,
            null,
            ['operation' => $operation]
        );
    }

    public static function databaseUnavailable(): self
    {
        return new self(
            'Database service unavailable for dashboard operations',
            503,
            null,
            ['service' => 'database']
        );
    }
}
