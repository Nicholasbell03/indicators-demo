<?php

namespace App\Traits;

trait HasCurrency
{
    /**
     * Get the currency symbol for display purposes
     */
    public function getCurrencySymbolAttribute(): ?string
    {
        return $this->currency?->symbol();
    }
}
