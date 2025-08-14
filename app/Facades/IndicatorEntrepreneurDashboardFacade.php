<?php

declare(strict_types=1);

namespace App\Facades;

use App\Enums\SessionCategoryType;
use App\Services\Indicators\IndicatorAttendanceStatService;
use App\Services\Indicators\IndicatorDashboardGridService;
use App\Services\Indicators\IndicatorElementProgressService;
use App\Services\Indicators\IndicatorTaskListingService;
use Carbon\Carbon;

/**
 * Facade providing simplified access to entrepreneur dashboard indicators.
 *
 * This class implements the Facade design pattern, coordinating multiple
 * indicator services to provide a unified dashboard interface.
 */
final class IndicatorEntrepreneurDashboardFacade
{
    public function __construct(
        private IndicatorDashboardGridService $indicatorGridService,
        private IndicatorAttendanceStatService $indicatorAttendanceStatService,
        private IndicatorElementProgressService $indicatorElementProgressService,
        private IndicatorTaskListingService $indicatorTaskListingService
    ) {}

    public function getAllDashboardData(?Carbon $beforeDate = null): array
    {
        return array_merge(
            $this->getSuccessIndicatorList($beforeDate),
            $this->getComplianceIndicatorList($beforeDate),
            $this->getSuccessIndicatorSummaryTableData(),
            $this->getComplianceIndicatorSummaryTableData(),
            $this->getLearningAttendanceStats(),
            $this->getMentoringAttendanceStats(),
            $this->getElementProgressStats(),
        );
    }

    public function seatHasIndicatorTasks(): bool
    {
        return $this->indicatorTaskListingService->hasIndicatorTasks();
    }

    public function getSuccessIndicatorList(?Carbon $beforeDate = null): array
    {
        return $this->indicatorTaskListingService->getFormattedSuccessIndicatorsForUser($beforeDate);
    }

    public function getComplianceIndicatorList(?Carbon $beforeDate = null): array
    {
        return $this->indicatorTaskListingService->getFormattedComplianceIndicatorsForUser($beforeDate);
    }

    public function getSuccessIndicatorSummaryTableData(): array
    {
        return $this->indicatorGridService->getSuccessIndicatorsDashboardData();
    }

    public function getComplianceIndicatorSummaryTableData(): array
    {
        return $this->indicatorGridService->getComplianceIndicatorsDashboardData();
    }

    public function getLearningAttendanceStats(): ?array
    {
        return $this->indicatorAttendanceStatService->getAttendanceStats(SessionCategoryType::LEARNING);
    }

    public function getMentoringAttendanceStats(): ?array
    {
        return $this->indicatorAttendanceStatService->getAttendanceStats(SessionCategoryType::MENTORING);
    }

    public function getElementProgressStats(): ?array
    {
        return $this->indicatorElementProgressService->getConsolidatedStats();
    }
}
