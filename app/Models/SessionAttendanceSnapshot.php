<?php

namespace App\Models;

use App\Enums\SessionCategoryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionAttendanceSnapshot extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'contract_start_date' => 'date',
        'ignition_date' => 'date',
        'type' => SessionCategoryType::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function organisationProgrammeSeat(): BelongsTo
    {
        return $this->belongsTo(OrganisationProgrammeSeat::class);
    }
}
