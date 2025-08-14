<?php

namespace App\Observers;

use App\Models\IndicatorComplianceProgrammeMonth;
use App\Traits\DeletesRelatedIndicatorTasks;

class IndicatorComplianceProgrammeMonthObserver
{
    use DeletesRelatedIndicatorTasks;

    public function saving(IndicatorComplianceProgrammeMonth $indicatorComplianceProgrammeMonth)
    {
        $this->checkMonthIsValid($indicatorComplianceProgrammeMonth);
    }

    private function checkMonthIsValid(IndicatorComplianceProgrammeMonth $indicatorComplianceProgrammeMonth)
    {
        $programme = $indicatorComplianceProgrammeMonth->indicatorComplianceProgramme->programme;

        if (! $programme) {
            throw new \InvalidArgumentException('Programme not found');
        }

        if ($indicatorComplianceProgrammeMonth->programme_month < 1) {
            throw new \InvalidArgumentException('Month is not valid, it must be greater than 0');
        }

        if ($indicatorComplianceProgrammeMonth->programme_month > $programme->duration) {
            throw new \InvalidArgumentException('Month is not valid, it must be less than or equal to the programme duration, which is '.$programme->duration.' months');
        }
    }
}
