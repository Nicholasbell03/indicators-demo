<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a verifier role cannot be determined or loaded for a given verification level.
 */
final class RoleNotFoundForVerificationLevelException extends IndicatorVerificationException {}
