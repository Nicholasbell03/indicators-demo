<?php

declare(strict_types=1);

namespace App\Services\Indicators;

use App\Enums\IndicatorComplianceTypeEnum;
use App\Enums\IndicatorDisplayStatus;
use App\Enums\IndicatorTaskStatusEnum;
use App\Exceptions\IndicatorServiceException;
use App\Exceptions\InvalidIndicatorDataException;
use App\Models\IndicatorCompliance;
use App\Models\IndicatorComplianceProgramme;
use App\Models\IndicatorSuccess;
use App\Models\IndicatorSuccessProgramme;
use App\Models\IndicatorTask;
use App\Models\Organisation;
use App\Models\OrganisationProgrammeSeat;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IndicatorDashboardGridService
{
    /**
     * Cache TTL for dashboard data (in seconds)
     */
    private const CACHE_TTL = 300; // 5 minutes

    private User $entrepreneur;

    private Organisation $organisation;

    private Programme $programme;

    public function __construct(
        private OrganisationProgrammeSeat $seat
    ) {
        $this->entrepreneur = $seat->user;
        $this->organisation = $seat->organisation;
        $this->programme = $seat->programme;
    }

    /**
     * Get dashboard table data for success indicators
     *
     * @throws InvalidIndicatorDataException
     */
    public function getSuccessIndicatorsDashboardData(): array
    {
        $this->validateUserInput();

        $cacheKey = "indicators_dashboard_success_user_{$this->entrepreneur->id}_org_{$this->organisation->id}_programme_{$this->programme->id}";

        try {
            return Cache::remember($cacheKey, self::CACHE_TTL, function () {
                return $this->getIndicatorsDashboardData(IndicatorSuccess::class);
            });
        } catch (\Exception $e) {
            Log::error('Failed to get success indicators dashboard data', [
                'entrepreneur_id' => $this->entrepreneur->id,
                'organisation_id' => $this->organisation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new IndicatorServiceException(
                'Failed to retrieve success indicators dashboard data',
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

    /**
     * Get dashboard table data for compliance indicators
     *
     * @throws InvalidIndicatorDataException
     */
    public function getComplianceIndicatorsDashboardData(): array
    {
        $this->validateUserInput();

        $cacheKey = "indicators_dashboard_compliance_user_{$this->entrepreneur->id}_org_{$this->organisation->id}_programme_{$this->programme->id}";

        try {
            return Cache::remember($cacheKey, self::CACHE_TTL, function () {
                return $this->getIndicatorsDashboardData(IndicatorCompliance::class);
            });
        } catch (\Exception $e) {
            Log::error('Failed to get compliance indicators dashboard data', [
                'entrepreneur_id' => $this->entrepreneur->id,
                'organisation_id' => $this->organisation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new IndicatorServiceException(
                'Failed to retrieve compliance indicators dashboard data',
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

    /**
     * Private method to build dashboard data structure
     *
     * @throws InvalidIndicatorDataException
     */
    private function getIndicatorsDashboardData(string $indicator): array
    {
        try {
            $this->validateIndicatorType($indicator);

            return $this->fetchDashboardData($indicator);

        } catch (\Exception $e) {
            Log::error('Unexpected error generating indicators dashboard data', [
                'entrepreneur_id' => $this->entrepreneur->id,
                'organisation_id' => $this->organisation->id,
                'indicator_type' => $indicator,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new IndicatorServiceException(
                'Failed to generate dashboard data',
                500,
                $e,
                [
                    'entrepreneur_id' => $this->entrepreneur->id,
                    'organisation_id' => $this->organisation->id,
                    'indicator_type' => $indicator,
                ]
            );
        }
    }

    /**
     * Execute the dashboard database queries
     */
    private function fetchDashboardData(string $indicator): array
    {
        $indicatorProgrammes = $this->fetchIndicatorProgrammes($indicator);
        $existingTasks = $this->fetchExistingTasks($indicatorProgrammes, $indicator);

        return $this->buildDashboardStructure(
            $indicatorProgrammes,
            $existingTasks,
            range(1, $this->programme->period),
            $this->entrepreneur->getCurrentProgrammeMonth() ?? 0,
            $this->programme->period,
            $indicator === IndicatorSuccess::class ? 'indicatorSuccess' : 'indicatorCompliance'
        );
    }

    /**
     * Fetch indicator programmes with optimized eager loading
     */
    private function fetchIndicatorProgrammes(string $indicator): Collection
    {
        if ($indicator === IndicatorSuccess::class) {
            return IndicatorSuccessProgramme::query()
                ->where('programme_id', $this->programme->id)
                ->published()
                ->with(['indicator', 'months'])
                ->get();
        }

        if ($indicator === IndicatorCompliance::class) {
            return IndicatorComplianceProgramme::query()
                ->where('programme_id', $this->programme->id)
                ->published()
                ->whereHas('indicator', function ($query) {
                    $query->where('type', IndicatorComplianceTypeEnum::OTHER->value);
                })
                ->with(['indicator', 'months'])
                ->get();
        }

        Log::error('Invalid indicator type', [
            'indicator' => $indicator,
            'programme_id' => $this->programme->id,
        ]);

        return collect();
    }

    /**
     * Fetch existing tasks for the indicator programmes
     */
    private function fetchExistingTasks(Collection $indicatorProgrammes, string $indicator): Collection
    {
        if ($indicatorProgrammes->isEmpty()) {
            return collect();
        }

        $monthIds = $indicatorProgrammes->flatMap(fn ($ip) => $ip->months->pluck('id'))->unique();

        return IndicatorTask::select('id', 'indicatable_month_id', 'status', 'is_achieved', 'due_date')
            ->where('entrepreneur_id', $this->entrepreneur->id)
            ->where('organisation_id', $this->organisation->id)
            ->where('programme_id', $this->programme->id)
            ->whereIn('indicatable_month_id', $monthIds)
            ->where('indicatable_type', $indicator === IndicatorSuccess::class ? IndicatorSuccess::class : IndicatorCompliance::class)
            ->get()
            ->keyBy('indicatable_month_id');
    }

    /**
     * Build the final dashboard data structure
     */
    private function buildDashboardStructure(Collection $indicatorProgrammes, Collection $existingTasks, array $programmeMonths, int $currentMonth, int $programmeDuration, string $indicatorType): array
    {
        $indicators = [];

        if ($indicatorProgrammes->isEmpty()) {
            return $this->getEmptyDashboardStructure();
        }

        foreach ($indicatorProgrammes as $indicatorProgramme) {
            $indicator = $indicatorProgramme->indicator;
            if (! $indicator) {
                continue;
            }

            $monthsCollection = $indicatorProgramme->months;
            $dueMonths = $monthsCollection->pluck('programme_month')->flip(); // Use flip() for O(1) lookups
            $monthsLookup = $monthsCollection->keyBy('programme_month'); // Pre-index by month

            $monthsData = [];
            foreach ($programmeMonths as $month) {
                $monthRecord = $monthsLookup->get($month);
                $task = $monthRecord ? $existingTasks->get($monthRecord->id) : null;
                $displayStatus = $this->calculateDisplayStatus($month, $dueMonths, $task, $currentMonth);

                $monthsData[$month] = [
                    'month' => $month,
                    'status' => $displayStatus->label(),
                    'task_id' => $task?->id,
                    'due_date' => $task?->due_date?->toISOString(),
                ];
            }

            $indicators[] = [
                'id' => $indicator->id,
                'name' => $indicator->title,
                'months' => $monthsData,
            ];
        }

        return [
            'indicators' => $indicators,
            'programmeMonths' => $programmeMonths,
            'currentMonth' => $currentMonth,
            'programmeDuration' => $programmeDuration,
            'type' => $indicatorType,
        ];
    }

    /**
     * Calculate display status for a specific month/indicator combination
     */
    private function calculateDisplayStatus(int $month, Collection $dueMonthsFlipped, ?IndicatorTask $task, int $currentMonth): IndicatorDisplayStatus
    {
        if (! isset($dueMonthsFlipped[$month])) {
            return IndicatorDisplayStatus::NOT_APPLICABLE;
        }

        if (! $task) {
            if ($month > $currentMonth) {
                return IndicatorDisplayStatus::NOT_YET_DUE;
            }

            Log::debug('Task not found for month : '.$month, [
                'month' => $month,
                'currentMonth' => $currentMonth,
                'programme_id' => $this->programme->id,
                'entrepreneur_id' => $this->entrepreneur->id,
            ]);

            return IndicatorDisplayStatus::NOT_APPLICABLE;
        }

        return match ($task->status) {
            IndicatorTaskStatusEnum::COMPLETED => $task->is_achieved ? IndicatorDisplayStatus::ACHIEVED : IndicatorDisplayStatus::NOT_ACHIEVED,
            IndicatorTaskStatusEnum::SUBMITTED => IndicatorDisplayStatus::VERIFYING,
            IndicatorTaskStatusEnum::PENDING,
            IndicatorTaskStatusEnum::OVERDUE => IndicatorDisplayStatus::NOT_SUBMITTED,
            IndicatorTaskStatusEnum::NEEDS_REVISION => IndicatorDisplayStatus::NEEDS_REVISION,
        };
    }

    /**
     * Get empty dashboard structure for error cases
     */
    private function getEmptyDashboardStructure(): array
    {
        return [
            'indicators' => [],
            'programmeMonths' => [],
            'currentMonth' => null,
            'programmeDuration' => 0,
        ];
    }

    /**
     * Validate user input
     *
     * @throws InvalidIndicatorDataException
     */
    private function validateUserInput(): void
    {
        if (! $this->entrepreneur || ! $this->entrepreneur->id || $this->entrepreneur->id <= 0) {
            throw InvalidIndicatorDataException::invalidUser($this->entrepreneur?->id ?? 0);
        }

        if (! $this->organisation || ! $this->organisation->id || $this->organisation->id <= 0) {
            throw InvalidIndicatorDataException::invalidOrganisation($this->organisation?->id ?? 0);
        }
    }

    /**
     * Validate indicator type
     *
     * @throws InvalidIndicatorDataException
     */
    private function validateIndicatorType(string $indicator): void
    {
        $validTypes = [IndicatorSuccess::class, IndicatorCompliance::class];

        if (! in_array($indicator, $validTypes)) {
            throw InvalidIndicatorDataException::invalidIndicatorType($indicator);
        }
    }

    public function flushCache(): void
    {
        Cache::forget("indicators_dashboard_success_user_{$this->entrepreneur->id}_org_{$this->organisation->id}_programme_{$this->programme->id}");
        Cache::forget("indicators_dashboard_compliance_user_{$this->entrepreneur->id}_org_{$this->organisation->id}_programme_{$this->programme->id}");
    }
}
