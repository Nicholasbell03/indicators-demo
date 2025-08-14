<?php

namespace App\Models;

use App\Enums\CurrencyEnum;
use App\Enums\IndicatorComplianceTypeEnum;
use App\Enums\IndicatorLevelEnum;
use App\Enums\IndicatorResponseFormatEnum;
use App\Http\Traits\ScopedToTenantTrait;
use App\Observers\IndicatorComplianceObserver;
use App\Traits\HasCurrency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndicatorCompliance extends Model
{
    use HasCurrency;
    use HasFactory;
    use ScopedToTenantTrait;
    use SoftDeletes;

    protected $casts = [
        'level' => IndicatorLevelEnum::class,
        'type' => IndicatorComplianceTypeEnum::class,
        'response_format' => IndicatorResponseFormatEnum::class,
        'currency' => CurrencyEnum::class,
    ];

    protected $guarded = [];

    public static function boot()
    {
        parent::boot();
        self::observe(IndicatorComplianceObserver::class);
    }

    public function tenantPortfolio(): BelongsTo
    {
        return $this->belongsTo(TenantPortfolio::class);
    }

    public function tenantCluster(): BelongsTo
    {
        return $this->belongsTo(TenantCluster::class);
    }

    public function responsibleRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'responsible_role_id');
    }

    public function verifier1Role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'verifier_1_role_id');
    }

    public function verifier2Role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'verifier_2_role_id');
    }

    public function programmes(): BelongsToMany
    {
        return $this->belongsToMany(Programme::class, 'indicator_compliance_programme')->withPivot('status');
    }

    public function requiresVerification(): bool
    {
        return $this->verifier_1_role_id !== null || $this->verifier_2_role_id !== null;
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(IndicatorTask::class);
    }
}
