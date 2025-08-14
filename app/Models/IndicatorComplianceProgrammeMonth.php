<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class IndicatorComplianceProgrammeMonth extends Model
{
    use HasFactory;

    protected $table = 'indicator_compliance_programme_months';

    protected $guarded = [];

    public function indicatorComplianceProgramme(): BelongsTo
    {
        return $this->belongsTo(IndicatorComplianceProgramme::class);
    }

    public function indicatorCompliance(): HasOneThrough
    {
        return $this->hasOneThrough(
            IndicatorCompliance::class,
            IndicatorComplianceProgramme::class,
            'id', // Foreign key on IndicatorComplianceProgramme table
            'id', // Foreign key on IndicatorCompliance table
            'indicator_compliance_programme_id', // Local key on this table
            'indicator_compliance_id' // Local key on IndicatorComplianceProgramme table
        );
    }

    /**
     * Duplicate, but consistent with the IndicatorSuccessProgrammeMonth::indicator() method.
     */
    public function indicator(): HasOneThrough
    {
        return $this->hasOneThrough(
            IndicatorCompliance::class,
            IndicatorComplianceProgramme::class,
            'id', // Foreign key on IndicatorComplianceProgramme table
            'id', // Foreign key on IndicatorCompliance table
            'indicator_compliance_programme_id', // Local key on this table
            'indicator_compliance_id' // Local key on IndicatorComplianceProgramme table
        );
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(IndicatorTask::class);
    }
}
