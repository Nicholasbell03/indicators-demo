<?php

namespace App\Models;

use App\Enums\IndicatorProgrammeStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class IndicatorSuccessProgramme extends Pivot
{
    use HasFactory;

    protected $table = 'indicator_success_programme';

    protected $guarded = [];

    public $timestamps = true;

    public $incrementing = true;

    protected $casts = [
        'status' => IndicatorProgrammeStatusEnum::class,
    ];

    public static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            $model->status ??= IndicatorProgrammeStatusEnum::PENDING->value;
        });
    }

    public function indicatorSuccess(): BelongsTo
    {
        return $this->belongsTo(IndicatorSuccess::class, 'indicator_success_id');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(IndicatorSuccess::class, 'indicator_success_id');
    }

    public function programme()
    {
        return $this->belongsTo(Programme::class);
    }

    public function months()
    {
        return $this->hasMany(IndicatorSuccessProgrammeMonth::class, 'indicator_success_programme_id', 'id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', IndicatorProgrammeStatusEnum::PUBLISHED->value);
    }

    public function scopeUnpublished($query)
    {
        return $query->where('status', IndicatorProgrammeStatusEnum::PENDING->value);
    }
}
