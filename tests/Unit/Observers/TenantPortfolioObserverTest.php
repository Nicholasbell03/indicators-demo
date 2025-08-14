<?php

use App\Models\TenantCluster;
use App\Models\TenantPortfolio;
use App\Observers\TenantPortfolioObserver;

beforeEach(function () {
    $this->observer = new TenantPortfolioObserver;
});

it('sets slug based on name when saving', function () {
    $portfolio = new TenantPortfolio(['name' => 'Test Portfolio Name']);

    $this->observer->saving($portfolio);

    expect($portfolio->slug)->toBe('test-portfolio-name');
});

it('allows updating portfolio with managing tenant cluster', function () {
    $tenantCluster = TenantCluster::factory()->create();
    $portfolio = TenantPortfolio::factory()->create([
        'managing_tenant_cluster_id' => $tenantCluster->id,
    ]);
    $tenantCluster->tenant_portfolio_id = $portfolio->id;

    // Should not throw exception
    expect(fn () => $this->observer->updating($portfolio))->not->toThrow(Exception::class);
});

it('allows updating portfolio without managing tenant cluster when no tenant clusters exist', function () {
    $portfolio = TenantPortfolio::factory()->create([
        'managing_tenant_cluster_id' => null,
    ]);

    // Should not throw exception
    expect(fn () => $this->observer->updating($portfolio))->not->toThrow(Exception::class);
});

it('prevents updating portfolio without managing tenant cluster when tenant clusters exist', function () {
    $portfolio = TenantPortfolio::factory()->create([
        'managing_tenant_cluster_id' => null,
    ]);
    $tenantCluster1 = TenantCluster::factory()->create([
        'tenant_portfolio_id' => $portfolio->id,
    ]);
    $tenantCluster2 = TenantCluster::factory()->create([
        'tenant_portfolio_id' => $portfolio->id,
    ]);

    // Should throw exception
    expect(fn () => $this->observer->updating($portfolio))
        ->toThrow(Exception::class, 'A tenant portfolio must have a managing tenant cluster');
});
