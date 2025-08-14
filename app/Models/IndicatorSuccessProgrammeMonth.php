<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class IndicatorSuccessProgrammeMonth extends Model
{
    use HasFactory;

    protected $table = 'indicator_success_programme_months';

    protected $guarded = [];

    public function indicatorSuccessProgramme(): BelongsTo
    {
        return $this->belongsTo(IndicatorSuccessProgramme::class);
    }

    public function indicatorSuccess(): HasOneThrough
    {
        return $this->hasOneThrough(
            IndicatorSuccess::class,
            IndicatorSuccessProgramme::class,
            'id', // Foreign key on IndicatorSuccessProgramme table
            'id', // Foreign key on IndicatorSuccess table
            'indicator_success_programme_id', // Local key on this table
            'indicator_success_id' // Local key on IndicatorSuccessProgramme table
        );
    }

    /**
     * Duplicate, but consistent with the IndicatorComplianceProgrammeMonth::indicator() method.
     */
    public function indicator(): HasOneThrough
    {
        return $this->hasOneThrough(
            IndicatorSuccess::class,
            IndicatorSuccessProgramme::class,
            'id', // Foreign key on IndicatorSuccessProgramme table
            'id', // Foreign key on IndicatorSuccess table
            'indicator_success_programme_id', // Local key on this table
            'indicator_success_id' // Local key on IndicatorSuccessProgramme table
        );
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(IndicatorTask::class);
    }
}
