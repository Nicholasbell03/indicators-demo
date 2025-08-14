<?php

use App\Models\IndicatorSuccess;
use App\Models\Organisation;
use App\Models\Partner;
use App\Models\Tenant;
use App\Models\TenantCluster;
use App\Models\TenantPortfolio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TestModelWithoutTenantSupport extends Model
{
    use App\Http\Traits\ScopedToTenantTrait;

    protected $table = 'test_models_without_tenant_support';

    protected $fillable = ['name'];

    public $timestamps = false;
}

beforeEach(function () {
    // Create test table for model without tenant support in each test process
    if (! Schema::hasTable('test_models_without_tenant_support')) {
        Schema::create('test_models_without_tenant_support', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }

    // Create test data structure
    $this->landlordTenant = Tenant::factory()->create();
    config(['multitenancy.landlord_id' => $this->landlordTenant->id]);

    $this->tenantPortfolio = TenantPortfolio::factory()->create();
    $this->tenantCluster = TenantCluster::factory()->create([
        'tenant_portfolio_id' => $this->tenantPortfolio->id,
    ]);

    $this->tenant1 = Tenant::factory()->create([
        'tenant_cluster_id' => $this->tenantCluster->id,
    ]);
    $this->tenant2 = Tenant::factory()->create([
        'tenant_cluster_id' => $this->tenantCluster->id,
    ]);

    // Different cluster/portfolio
    $this->otherPortfolio = TenantPortfolio::factory()->create();
    $this->otherCluster = TenantCluster::factory()->create([
        'tenant_portfolio_id' => $this->otherPortfolio->id,
    ]);
    $this->otherTenant = Tenant::factory()->create([
        'tenant_cluster_id' => $this->otherCluster->id,
    ]);
    // Set current tenant
    app()->instance('currentTenant', $this->tenant1);
});

afterEach(function () {
    // Clean up test table after each test to avoid interference
    if (Schema::hasTable('test_models_without_tenant_support')) {
        Schema::dropIfExists('test_models_without_tenant_support');
    }
});

describe('bootScopedToTenantTrait', function () {
    it('auto-sets tenant_id when creating model', function () {
        $partner = Partner::factory()->create();

        expect($partner->tenant_id)->toBe($this->tenant1->id);
    });

    it('does not override existing tenant_id', function () {
        $partner = Partner::factory()->create([
            'tenant_id' => $this->tenant2->id,
        ]);

        expect($partner->tenant_id)->toBe($this->tenant2->id);
    });

    it('uses landlord tenant when no current tenant available', function () {
        app()->forgetInstance('currentTenant');

        $partner = Partner::factory()->create();

        expect($partner->tenant_id)->toBe($this->landlordTenant->id);
    });

    it('does not attempt to set tenant_id on models without the column', function () {
        // Organisation model uses tenants() relationship instead of tenant_id column
        $organisation = Organisation::factory()->create();

        // The main assertion is that no exception was thrown
        // and no tenant_id was set (since the column doesn't exist)
        expect($organisation)->not->toHaveKey('tenant_id');
        expect($organisation->exists)->toBeTrue();
    });

    it('handles models with neither tenant_id column nor tenants relationship gracefully', function () {
        // This should not throw an exception even though the model lacks tenant support
        $model = new TestModelWithoutTenantSupport(['name' => 'test']);
        $model->save();

        expect($model->exists)->toBeTrue();
        expect($model)->not->toHaveKey('tenant_id');
    });
});

describe('scopeOfCurrentTenant', function () {
    it('filters records by current tenant', function () {
        $partner1 = Partner::factory()->create();
        $partner2 = Partner::factory()->create([
            'tenant_id' => $this->tenant2->id,
        ]);

        $results = Partner::ofCurrentTenant()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($partner1->id);
    });

    it('returns all records when current tenant is landlord and restrict_landlord is false', function () {
        Partner::query()->delete();
        app()->instance('currentTenant', $this->landlordTenant);

        Partner::factory()->count(3)->create([
            'tenant_id' => $this->tenant2->id,
        ]);

        $results = Partner::ofCurrentTenant()->get();

        expect($results)->toHaveCount(3);
    });

    it('restricts landlord results when restrict_landlord is true', function () {
        app()->instance('currentTenant', $this->landlordTenant);

        $partner1 = Partner::factory()->create([
            'tenant_id' => $this->tenant2->id,
        ]);
        $partner2 = Partner::factory()->create([
            'tenant_id' => $this->tenant1->id,
        ]);

        $landlordPartner = Partner::factory()->create([
            'tenant_id' => $this->landlordTenant->id,
        ]);

        $results = Partner::ofCurrentTenant(restrict_landlord: true)->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($landlordPartner->id);
    });

    it('returns all records when no current tenant', function () {
        Partner::query()->delete();
        app()->forgetInstance('currentTenant');

        $partner1 = Partner::factory()->create();
        $partner2 = Partner::factory()->create([
            'tenant_id' => $this->tenant2->id,
        ]);

        $results = Partner::ofCurrentTenant()->get();

        expect($results)->toHaveCount(2);
    });
});

describe('scopeOfLandlord', function () {
    it('filters records by landlord tenant', function () {
        $partner1 = Partner::factory()->create([
            'tenant_id' => $this->tenant2->id,
        ]);
        $partner2 = Partner::factory()->create([
            'tenant_id' => $this->tenant1->id,
        ]);

        $landlordPartner = Partner::factory()->create([
            'tenant_id' => $this->landlordTenant->id,
        ]);

        $results = Partner::ofLandlord()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($landlordPartner->id);
    });
});

describe('scopeTenantsOfCurrentTenantCluster', function () {
    it('filters records for models with tenant_id column', function () {
        $partner1 = Partner::factory()->create([
            'tenant_id' => $this->tenant1->id,
        ]);
        $partner2 = Partner::factory()->create([
            'tenant_id' => $this->tenant2->id,
        ]);
        $partnerOther = Partner::factory()->create([
            'tenant_id' => $this->otherTenant->id,
        ]);

        $results = Partner::tenantsOfCurrentTenantCluster()->get();

        expect($results)->toHaveCount(2);
        expect($results->pluck('id')->toArray())->toContain($partner1->id, $partner2->id);
        expect($results->pluck('id')->toArray())->not->toContain($partnerOther->id);
    });

    it('filters records for models with tenants relationship', function () {
        $org1 = Organisation::factory()->create();
        $org2 = Organisation::factory()->create();
        $orgOther = Organisation::factory()->create();

        // Attach tenants via pivot
        $org1->tenants()->attach($this->tenant1->id, ['is_primary' => true]);
        $org2->tenants()->attach($this->tenant2->id, ['is_primary' => true]);
        $orgOther->tenants()->attach($this->otherTenant->id, ['is_primary' => true]);

        $results = Organisation::tenantsOfCurrentTenantCluster()->get();

        expect($results)->toHaveCount(2);
        expect($results->pluck('id')->toArray())->toContain($org1->id, $org2->id);
        expect($results->pluck('id')->toArray())->not->toContain($orgOther->id);
    });

    it('returns current tenant records when tenant has no cluster', function () {
        $tenantWithoutCluster = Tenant::factory()->create(); // No cluster assigned
        app()->instance('currentTenant', $tenantWithoutCluster);

        $partnerCurrent = Partner::factory()->create([
            'tenant_id' => $tenantWithoutCluster->id,
        ]);
        $partnerOther = Partner::factory()->create([
            'tenant_id' => $this->tenant1->id,
        ]);

        $results = Partner::tenantsOfCurrentTenantCluster()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($partnerCurrent->id);
    });

    it('returns empty when no current tenant', function () {
        app()->forgetInstance('currentTenant');

        Partner::factory()->create([
            'tenant_id' => $this->tenant1->id,
        ]);

        $results = Partner::tenantsOfCurrentTenantCluster()->get();

        expect($results)->toHaveCount(0);
    });

    it('returns empty result for models with neither tenant_id nor tenants relationship', function () {
        // Create some test data
        TestModelWithoutTenantSupport::create(['name' => 'test1']);
        TestModelWithoutTenantSupport::create(['name' => 'test2']);

        $results = TestModelWithoutTenantSupport::tenantsOfCurrentTenantCluster()->get();

        expect($results)->toBeEmpty();
    });
});

describe('scopeTenantsOfCurrentTenantPortfolio', function () {
    it('filters records for models with tenant_id column', function () {
        $partner1 = Partner::factory()->create([
            'tenant_id' => $this->tenant1->id,
        ]);
        $partner2 = Partner::factory()->create([
            'tenant_id' => $this->tenant2->id,
        ]);
        $partnerOther = Partner::factory()->create([
            'tenant_id' => $this->otherTenant->id,
        ]);

        $results = Partner::tenantsOfCurrentTenantPortfolio()->get();

        expect($results)->toHaveCount(2);
        expect($results->pluck('id')->toArray())->toContain($partner1->id, $partner2->id);
        expect($results->pluck('id')->toArray())->not->toContain($partnerOther->id);
    });

    it('filters records for models with tenants relationship', function () {
        $org1 = Organisation::factory()->create();
        $org2 = Organisation::factory()->create();
        $orgOther = Organisation::factory()->create();

        // Attach tenants via pivot
        $org1->tenants()->attach($this->tenant1->id, ['is_primary' => true]);
        $org2->tenants()->attach($this->tenant2->id, ['is_primary' => true]);
        $orgOther->tenants()->attach($this->otherTenant->id, ['is_primary' => true]);

        $results = Organisation::tenantsOfCurrentTenantPortfolio()->get();

        expect($results)->toHaveCount(2);
        expect($results->pluck('id')->toArray())->toContain($org1->id, $org2->id);
        expect($results->pluck('id')->toArray())->not->toContain($orgOther->id);
    });

    it('returns current tenant records when tenant has no cluster or portfolio', function () {
        $tenantWithoutCluster = Tenant::factory()->create(); // No cluster assigned
        app()->instance('currentTenant', $tenantWithoutCluster);

        $partnerCurrent = Partner::factory()->create([
            'tenant_id' => $tenantWithoutCluster->id,
        ]);
        $partnerOther = Partner::factory()->create([
            'tenant_id' => $this->tenant1->id,
        ]);

        $results = Partner::tenantsOfCurrentTenantPortfolio()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($partnerCurrent->id);
    });

    it('returns current tenant records when cluster has no portfolio', function () {
        // Create a cluster without portfolio
        $clusterWithoutPortfolio = TenantCluster::factory()->create([
            'tenant_portfolio_id' => null,
        ]);
        $tenantWithoutPortfolio = Tenant::factory()->create([
            'tenant_cluster_id' => $clusterWithoutPortfolio->id,
        ]);
        app()->instance('currentTenant', $tenantWithoutPortfolio);

        $partnerCurrent = Partner::factory()->create([
            'tenant_id' => $tenantWithoutPortfolio->id,
        ]);
        $partnerOther = Partner::factory()->create([
            'tenant_id' => $this->tenant1->id,
        ]);

        $results = Partner::tenantsOfCurrentTenantPortfolio()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($partnerCurrent->id);
    });

    it('returns empty when no current tenant', function () {
        app()->forgetInstance('currentTenant');

        Partner::factory()->create([
            'tenant_id' => $this->tenant1->id,
        ]);

        $results = Partner::tenantsOfCurrentTenantPortfolio()->get();

        expect($results)->toHaveCount(0);
    });

    it('returns empty result for models with neither tenant_id nor tenants relationship', function () {
        // Create some test data
        TestModelWithoutTenantSupport::create(['name' => 'test1']);
        TestModelWithoutTenantSupport::create(['name' => 'test2']);

        $results = TestModelWithoutTenantSupport::tenantsOfCurrentTenantPortfolio()->get();

        expect($results)->toBeEmpty();
    });
});

describe('scopeClustersOfCurrentTenantPortfolio', function () {
    it('filters records by tenant_cluster_id in current portfolio', function () {
        $indicator1 = IndicatorSuccess::factory()->forCluster($this->tenantCluster->id)->create();
        $indicator2 = IndicatorSuccess::factory()->forCluster($this->otherCluster->id)->create();

        $results = IndicatorSuccess::clustersOfCurrentTenantPortfolio()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($indicator1->id);
    });

    it('returns empty when tenant has no cluster', function () {
        $tenantWithoutCluster = Tenant::factory()->create();
        app()->instance('currentTenant', $tenantWithoutCluster);

        IndicatorSuccess::factory()->forCluster($this->tenantCluster->id)->create();

        $results = IndicatorSuccess::clustersOfCurrentTenantPortfolio()->get();

        expect($results)->toHaveCount(0);
    });

    it('returns empty when no current tenant', function () {
        app()->forgetInstance('currentTenant');

        IndicatorSuccess::factory()->forCluster($this->tenantCluster->id)->create();

        $results = IndicatorSuccess::clustersOfCurrentTenantPortfolio()->get();

        expect($results)->toHaveCount(0);
    });
});

describe('scopeOfCurrentTenantCluster', function () {
    it('filters records by current tenant cluster', function () {
        $indicator1 = IndicatorSuccess::factory()->forCluster($this->tenantCluster->id)->create();
        $indicator2 = IndicatorSuccess::factory()->forCluster($this->otherCluster->id)->create();

        $results = IndicatorSuccess::ofCurrentTenantCluster()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($indicator1->id);
    });

    it('returns empty when tenant has no cluster', function () {
        $tenantWithoutCluster = Tenant::factory()->create();
        app()->instance('currentTenant', $tenantWithoutCluster);

        IndicatorSuccess::factory()->forCluster($this->tenantCluster->id)->create();

        $results = IndicatorSuccess::ofCurrentTenantCluster()->get();

        expect($results)->toHaveCount(0);
    });

    it('returns empty when no current tenant', function () {
        app()->forgetInstance('currentTenant');

        IndicatorSuccess::factory()->forCluster($this->tenantCluster->id)->create();

        $results = IndicatorSuccess::ofCurrentTenantCluster()->get();

        expect($results)->toHaveCount(0);
    });
});

describe('scopeOfCurrentTenantPortfolio', function () {
    it('filters records by current tenant portfolio', function () {
        $indicator1 = IndicatorSuccess::factory()->forPortfolio($this->tenantPortfolio->id)->create();
        $indicator2 = IndicatorSuccess::factory()->forPortfolio($this->otherPortfolio->id)->create();

        $results = IndicatorSuccess::ofCurrentTenantPortfolio()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($indicator1->id);
    });

    it('returns empty when tenant has no portfolio', function () {
        $tenantWithoutCluster = Tenant::factory()->create();
        app()->instance('currentTenant', $tenantWithoutCluster);

        IndicatorSuccess::factory()->forPortfolio($this->tenantPortfolio->id)->create();

        $results = IndicatorSuccess::ofCurrentTenantPortfolio()->get();

        expect($results)->toHaveCount(0);
    });

    it('returns empty when no current tenant', function () {
        app()->forgetInstance('currentTenant');

        IndicatorSuccess::factory()->forPortfolio($this->tenantPortfolio->id)->create();

        $results = IndicatorSuccess::ofCurrentTenantPortfolio()->get();

        expect($results)->toHaveCount(0);
    });
});

describe('Integration tests', function () {
    it('works with real model that uses the trait', function () {
        // IndicatorSuccess already uses ScopedToTenantTrait
        $indicator = IndicatorSuccess::factory()->forCluster($this->tenantCluster->id)->create();

        // Test that the trait methods are available
        expect(method_exists($indicator, 'scopeOfCurrentTenantCluster'))->toBeTrue();
        expect(method_exists($indicator, 'scopeOfCurrentTenantPortfolio'))->toBeTrue();

        // Test actual usage
        $results = IndicatorSuccess::ofCurrentTenantCluster()->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($indicator->id);
    });
});
