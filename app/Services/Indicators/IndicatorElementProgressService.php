<?php

declare(strict_types=1);

namespace App\Services\Indicators;

use App\Enums\IndicatorComplianceTypeEnum;
use App\Exceptions\IndicatorServiceException;
use App\Models\IndicatorComplianceProgrammeMonth;
use App\Models\Organisation;
use App\Models\OrganisationProgrammeSeat;
use App\Models\Programme;
use App\Models\User;
use App\Services\ProgrammeElementProgressCalculationServiceFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class IndicatorElementProgressService
{
    private const CACHE_TTL = 300; // 5 minutes

    private User $entrepreneur;

    private Organisation $organisation;

    private Programme $programme;

    public function __construct(
        private OrganisationProgrammeSeat $seat,
        private ProgrammeElementProgressCalculationServiceFactory $programmeElementProgressCalculationServiceFactory
    ) {
        $this->entrepreneur = $seat->user;
        $this->organisation = $seat->organisation;
        $this->programme = $seat->programme;
    }

    public function getConsolidatedStats(): ?array
    {
        try {
            $programmeStats = $this->getElementProgressTargetsAndAchievedValues();
            $currentStats = $this->getCurrentElementProgressStats();
        } catch (\Exception $e) {
            Log::error('Error getting element progress stats', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return [
            'programme_stats' => $programmeStats,
            'current_stats' => $currentStats,
        ];
    }

    private function getElementProgressTargetsAndAchievedValues(): array
    {
        $cacheKey = "indicators_dashboard_element_progress_targets_and_achieved_values_{$this->entrepreneur->id}_{$this->organisation->id}";

        try {
            // Cache for 8 hours as this data does not change often
            return Cache::remember($cacheKey, 60 * 60 * 8, function () {
                $stats = DB::table('indicator_compliance_programme')
                    ->leftJoin('indicator_compliance_programme_months', 'indicator_compliance_programme.id', '=', 'indicator_compliance_programme_months.indicator_compliance_programme_id')
                    ->leftJoin('indicator_compliances', 'indicator_compliance_programme.indicator_compliance_id', '=', 'indicator_compliances.id')
                    ->join('indicator_tasks', function ($join) {
                        $join->on('indicator_tasks.indicatable_month_id', '=', 'indicator_compliance_programme_months.id')
                            ->where('indicator_tasks.indicatable_month_type', IndicatorComplianceProgrammeMonth::class)
                            ->where('indicator_tasks.entrepreneur_id', $this->entrepreneur->id)
                            ->where('indicator_tasks.organisation_id', $this->organisation->id)
                            ->where('indicator_tasks.programme_id', $this->programme->id);
                    })
                    ->join('indicator_submissions', 'indicator_tasks.id', '=', 'indicator_submissions.indicator_task_id')
                    ->where('indicator_compliance_programme.programme_id', $this->programme->id)
                    ->where('indicator_compliances.type', IndicatorComplianceTypeEnum::ELEMENT_PROGRESS->value)
                    ->select(
                        'indicator_compliance_programme_months.id as month_id',
                        'indicator_compliance_programme_months.programme_month as month',
                        'indicator_compliance_programme_months.target_value as target',
                        'indicator_tasks.id as task_id',
                        'indicator_submissions.value as progress',
                        'indicator_submissions.is_achieved as is_achieved'
                    )
                    ->get();

                return $stats->groupBy('month')->map(function ($group) {
                    return [
                        'month' => $group->first()->month,
                        'target' => $group->first()->target,
                        'progress' => $group->first()->progress,
                        'is_achieved' => $group->first()->is_achieved,
                    ];
                })->toArray();
            });
        } catch (\Exception $e) {
            Log::error('Error getting element progress targets and achieved values', [
                'entrepreneur_id' => $this->entrepreneur->id,
                'organisation_id' => $this->organisation->id,
                'programme_id' => $this->programme->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new IndicatorServiceException(
                'Failed to retrieve element progress targets and achieved values',
                500,
                $e,
                [
                    'entrepreneur_id' => $this->entrepreneur->id,
                    'organisation_id' => $this->organisation->id,
                    'programme_id' => $this->programme->id,
                ]
            );
        }
    }

    private function getCurrentElementProgressStats(): ?array
    {
        $cacheKey = "indicators_dashboard_element_progress_{$this->entrepreneur->id}_{$this->organisation->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $service = $this->programmeElementProgressCalculationServiceFactory->create($this->entrepreneur, $this->organisation, $this->programme);

            return $service->getConsolidatedProgress();
        });
    }
}
