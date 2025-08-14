<?php

declare(strict_types=1);

namespace App\Services\Indicators;

use App\Enums\SessionCategoryType;
use App\Models\IndicatorComplianceProgramme;
use App\Models\Organisation;
use App\Models\OrganisationProgrammeSeat;
use App\Models\Programme;
use App\Models\User;
use App\Services\ProgrammeSessionAttendanceServiceFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IndicatorAttendanceStatService
{
    private const CACHE_TTL = 300; // 5 minutes

    private User $entrepreneur;

    private Organisation $organisation;

    private Programme $programme;

    public function __construct(
        private OrganisationProgrammeSeat $seat,
        private ProgrammeSessionAttendanceServiceFactory $programmeSessionAttendanceServiceFactory
    ) {
        $this->entrepreneur = $seat->user;
        $this->organisation = $seat->organisation;
        $this->programme = $seat->programme;
    }

    /**
     * @throws \App\Exceptions\InvalidIndicatorDataException
     * @throws \App\Exceptions\IndicatorServiceException
     */
    public function getAttendanceStats(SessionCategoryType $type): ?array
    {
        $targetPercentage = $this->getTargetAttendancePercentageForCurrentMonth($type);
        if ($targetPercentage === null) {
            return null;
        }

        $cacheKey = "indicators_dashboard_attendance_{$this->entrepreneur->id}_{$this->organisation->id}_{$type->value}";

        $attendanceStats = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($type) {
            $service = $this->programmeSessionAttendanceServiceFactory->create($this->seat);

            return $service->getAttendanceStats($type, false);
        });

        if ($attendanceStats === null) {
            return null;
        }

        $attendanceStats['target_percentage'] = $targetPercentage;

        return $attendanceStats;
    }

    private function getTargetAttendancePercentageForCurrentMonth(SessionCategoryType $type): ?string
    {
        $currentProgrammeMonth = $this->entrepreneur->getCurrentProgrammeMonth();
        if (is_null($currentProgrammeMonth)) {
            Log::warning('Could not determine current programme month for entrepreneur.', [
                'entrepreneur_id' => $this->entrepreneur->id,
                'programme_id' => $this->programme->id,
            ]);

            return null;
        }

        $indicatorComplianceProgramme = IndicatorComplianceProgramme::query()
            ->where('programme_id', $this->programme->id)
            ->whereHas('indicator', function ($query) use ($type) {
                $query->where('type', $type->getCorrespondingIndicatorComplianceType()->value);
            })
            ->with(['months' => function ($query) use ($currentProgrammeMonth) {
                $query->where('programme_month', $currentProgrammeMonth);
            }])
            ->orderBy('id', 'desc')
            ->first();

        if (! $indicatorComplianceProgramme) {
            Log::debug('No indicator compliance programme record found for the following criteria', [
                'entrepreneur_id' => $this->entrepreneur->id,
                'programme_id' => $this->programme->id,
                'current_programme_month' => $currentProgrammeMonth,
                'type' => $type->getCorrespondingIndicatorComplianceType()->value,
            ]);

            return null;
        }

        $monthSetting = $indicatorComplianceProgramme->months->first();

        if (! $monthSetting) {
            Log::warning('No attendance target setting found for current programme month', [
                'entrepreneur_id' => $this->entrepreneur->id,
                'programme_id' => $this->programme->id,
                'current_programme_month' => $currentProgrammeMonth,
                'indicator_compliance_programme_id' => $indicatorComplianceProgramme->id,
            ]);

            return null;
        }

        return $monthSetting?->target_value;
    }
}
