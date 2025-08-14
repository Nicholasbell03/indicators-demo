<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when an IndicatorTask lacks a required association (indicatable/entrepreneur/organisation/programme).
 */
final class MissingIndicatorAssociationException extends IndicatorVerificationException {}
