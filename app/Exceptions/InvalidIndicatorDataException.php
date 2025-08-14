<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Exception thrown when indicator data is invalid or corrupted
 */
class InvalidIndicatorDataException extends IndicatorServiceException
{
    public static function invalidUser(int $userId): self
    {
        return new self(
            "Invalid or non-existent user provided: {$userId}",
            400,
            null,
            ['user_id' => $userId]
        );
    }

    public static function invalidOrganisation(int $organisationId): self
    {
        return new self(
            "Invalid or non-existent organisation provided: {$organisationId}",
            400,
            null,
            ['organisation_id' => $organisationId]
        );
    }

    public static function noProgramme(int $userId): self
    {
        return new self(
            "User has no current programme: {$userId}",
            404,
            null,
            ['user_id' => $userId]
        );
    }

    public static function invalidProgrammeDuration(int $duration): self
    {
        return new self(
            "Invalid programme duration: {$duration}. Must be between 1 and 60 months.",
            400,
            null,
            ['duration' => $duration]
        );
    }

    public static function invalidIndicatorType(string $type): self
    {
        return new self(
            "Invalid indicator type provided: {$type}",
            400,
            null,
            ['indicator_type' => $type]
        );
    }
}
