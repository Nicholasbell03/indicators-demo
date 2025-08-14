<?php

use App\Models\Tenant;
use App\Models\TenantCluster;
use App\Observers\TenantClusterObserver;

beforeEach(function () {
    $this->observer = new TenantClusterObserver;
});

it('sets slug based on name when saving', function () {
    $cluster = new TenantCluster(['name' => 'Test Cluster Name']);

    $this->observer->saving($cluster);

    expect($cluster->slug)->toBe('test-cluster-name');
});

it('allows updating cluster with parent tenant', function () {
    $cluster = TenantCluster::factory()->create();
    $tenant = Tenant::factory()->create([
        'tenant_cluster_id' => $cluster->id,
    ]);
    $cluster->parent_tenant_id = $tenant->id;

    // Should not throw exception
    expect(fn () => $this->observer->updating($cluster))->not->toThrow(Exception::class);
});

it('allows updating cluster without parent tenant when no tenants exist', function () {
    $tenant = Tenant::factory()->create([
        'tenant_cluster_id' => null,
    ]);
    $cluster = TenantCluster::factory()->create([
        'parent_tenant_id' => null,
    ]);

    // Should not throw exception
    expect(fn () => $this->observer->updating($cluster))->not->toThrow(Exception::class);
});

it('prevents updating cluster without parent tenant when tenants exist', function () {
    $cluster = TenantCluster::factory()->create([
        'parent_tenant_id' => null,
    ]);
    $tenant1 = Tenant::factory()->create([
        'tenant_cluster_id' => $cluster->id,
    ]);
    $tenant2 = Tenant::factory()->create([
        'tenant_cluster_id' => $cluster->id,
    ]);

    // Should throw exception
    expect(fn () => $this->observer->updating($cluster))
        ->toThrow(Exception::class, 'A tenant cluster must have a parent tenant');
});
