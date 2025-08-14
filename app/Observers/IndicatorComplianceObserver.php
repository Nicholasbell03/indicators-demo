<?php

namespace App\Observers;

use App\Models\IndicatorCompliance;
use App\Observers\Traits\DeletesRelatedIndicatorTasks;
use Illuminate\Support\Str;

class IndicatorComplianceObserver
{
    use DeletesRelatedIndicatorTasks;

    public function saving(IndicatorCompliance $indicatorCompliance)
    {
        $indicatorCompliance->slug = Str::slug($indicatorCompliance->title);

        $this->enforceTenantPortfolioAndCluster($indicatorCompliance);
    }

    private function enforceTenantPortfolioAndCluster(IndicatorCompliance $indicatorCompliance)
    {
        if ($indicatorCompliance->tenant_portfolio_id === null && $indicatorCompliance->tenant_cluster_id === null) {
            throw new \InvalidArgumentException('Either a tenant portfolio or cluster ID is required');
        }

        if ($indicatorCompliance->tenant_portfolio_id && $indicatorCompliance->tenant_cluster_id) {
            throw new \InvalidArgumentException('A compliance indicator cannot be associated with both a portfolio and a cluster directly.');
        }
    }
}
