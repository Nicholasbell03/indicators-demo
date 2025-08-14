<?php

declare(strict_types=1);

namespace App\Facades;

use App\Models\OrganisationProgrammeSeat;
use App\Services\Indicators\IndicatorAttendanceStatService;
use App\Services\Indicators\IndicatorDashboardGridService;
use App\Services\Indicators\IndicatorElementProgressService;
use App\Services\Indicators\IndicatorTaskListingService;
use App\Services\ProgrammeElementProgressCalculationServiceFactory;
use App\Services\ProgrammeSessionAttendanceServiceFactory;

class IndicatorEntrepreneurDashboardFacadeFactory
{
    public function create(OrganisationProgrammeSeat $seat): IndicatorEntrepreneurDashboardFacade
    {
        $attendanceServiceFactory = app(ProgrammeSessionAttendanceServiceFactory::class);
        $progressServiceFactory = app(ProgrammeElementProgressCalculationServiceFactory::class);

        $indicatorGridService = new IndicatorDashboardGridService($seat);
        $indicatorAttendanceStatService = new IndicatorAttendanceStatService($seat, $attendanceServiceFactory);
        $indicatorElementProgressService = new IndicatorElementProgressService($seat, $progressServiceFactory);
        $indicatorTaskListingService = new IndicatorTaskListingService($seat);

        return new IndicatorEntrepreneurDashboardFacade(
            $indicatorGridService,
            $indicatorAttendanceStatService,
            $indicatorElementProgressService,
            $indicatorTaskListingService
        );
    }
}
