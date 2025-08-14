<?php

namespace App\Observers;

use App\Enums\UserRoles;
use App\Models\IndicatorSuccess;
use App\Models\Role;
use App\Traits\DeletesRelatedIndicatorTasks;
use Illuminate\Support\Str;

class IndicatorSuccessObserver
{
    use DeletesRelatedIndicatorTasks;

    public function creating(IndicatorSuccess $indicatorSuccess)
    {
        $userRole = Role::where('name', UserRoles::USER->value)->first();
        if (! $userRole) {
            throw new \Exception('User role not found');
        }

        $indicatorSuccess->responsible_role_id = $userRole->id;
    }

    public function saving(IndicatorSuccess $indicatorSuccess)
    {
        $indicatorSuccess->slug = Str::slug($indicatorSuccess->title);

        $this->enforceTenantPortfolioAndCluster($indicatorSuccess);
    }

    private function enforceTenantPortfolioAndCluster(IndicatorSuccess $indicatorSuccess)
    {
        if ($indicatorSuccess->tenant_portfolio_id === null && $indicatorSuccess->tenant_cluster_id === null) {
            throw new \InvalidArgumentException('Either a tenant portfolio or cluster ID is required');
        }

        if ($indicatorSuccess->tenant_portfolio_id && $indicatorSuccess->tenant_cluster_id) {
            throw new \InvalidArgumentException('An indicator success cannot be associated with both a portfolio and a cluster directly.');
        }
    }
}
