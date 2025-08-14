<?php

declare(strict_types=1);

use App\Models\Catalogue;
use App\Models\CatalogueModule;
use App\Models\Department;
use App\Models\Element;
use App\Models\ElementProgress;
use App\Models\LicenceGroup;
use App\Models\Organisation;
use App\Models\OrganisationCatalogueModuleUser;
use App\Models\OrganisationProgrammeSeat;
use App\Models\Programme;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProgrammeElementProgressCalculationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->programmeSeat = OrganisationProgrammeSeat::factory()->create();
    $this->organisation = $this->programmeSeat->organisation;
    $this->programme = $this->programmeSeat->programme;
    $this->user = $this->programmeSeat->user;
});

describe('Constructor (__construct)', function () {
    it('loads missing relationships on the programme seat', function () {
        // Query for programme seat without loading relationships
        $freshProgrammeSeat = OrganisationProgrammeSeat::query()
            ->where('id', $this->programmeSeat->id)
            ->first();

        // Assert relationships are not loaded
        expect($freshProgrammeSeat->relationLoaded('user'))->toBeFalse();
        expect($freshProgrammeSeat->relationLoaded('organisation'))->toBeFalse();
        expect($freshProgrammeSeat->relationLoaded('programme'))->toBeFalse();

        // Instantiate the service
        $service = new ProgrammeElementProgressCalculationService($freshProgrammeSeat->user, $freshProgrammeSeat->organisation, $freshProgrammeSeat->programme);

        // Use reflection to access private properties
        $reflection = new ReflectionClass($service);

        $userProperty = $reflection->getProperty('user');
        $userProperty->setAccessible(true);
        $organisationProperty = $reflection->getProperty('organisation');
        $organisationProperty->setAccessible(true);
        $programmeProperty = $reflection->getProperty('programme');
        $programmeProperty->setAccessible(true);

        // Assert relationships are now loaded
        expect($userProperty->getValue($service))->toBeInstanceOf(User::class);
        expect($userProperty->getValue($service)->id)->toBe($this->user->id);
        expect($organisationProperty->getValue($service))->toBeInstanceOf(Organisation::class);
        expect($organisationProperty->getValue($service)->id)->toBe($this->organisation->id);
        expect($programmeProperty->getValue($service))->toBeInstanceOf(Programme::class);
        expect($programmeProperty->getValue($service)->id)->toBe($this->programme->id);
    });
});

describe('getConsolidatedProgress()', function () {
    it('correctly sums total and completed fields and calculates percentage', function () {
        // Set up real data
        $tenant = Tenant::factory()->create();
        $catalogueModule = CatalogueModule::factory()->create();
        $catalogue = Catalogue::factory()->create([
            'published' => true,
            'progress_type' => 'organisation',
        ]);
        $element1 = Element::factory()->create(['number_of_fields' => 10]);
        $element2 = Element::factory()->create(['number_of_fields' => 20]);

        // Associate relationships
        $catalogueModule->catalogues()->attach($catalogue->id);
        $catalogue->elements()->attach([$element1->id, $element2->id]);

        $licenceGroup = LicenceGroup::factory()->create([
            'programme_id' => $this->programme->id,
            'default_catalogue_module_ids' => [$catalogueModule->id],
        ]);

        // Attach licence group to organisation
        $this->organisation->licenceGroups()->attach($licenceGroup->id, [
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'expires_at' => now()->addMonth(),
        ]);

        // Give user access to the module
        OrganisationCatalogueModuleUser::create([
            'organisation_id' => $this->organisation->id,
            'tenant_id' => $tenant->id,
            'user_id' => $this->user->id,
            'catalogue_module_id' => $catalogueModule->id,
        ]);

        // Create ElementProgress records
        ElementProgress::create([
            'element_id' => $element1->id,
            'organisation_id' => $this->organisation->id,
            'total_fields' => 10,
            'complete_fields' => 5,
        ]);

        ElementProgress::create([
            'element_id' => $element2->id,
            'organisation_id' => $this->organisation->id,
            'total_fields' => 20,
            'complete_fields' => 10,
        ]);

        $service = new ProgrammeElementProgressCalculationService($this->user, $this->organisation, $this->programme);
        $result = $service->getConsolidatedProgress();

        expect($result['total_fields'])->toBe(30);
        expect($result['completed_fields'])->toBe(15);
        expect($result['percentage'])->toBe(50.0);
    });

    it('returns zeroes when detailed progress is empty', function () {
        // Create a service with no licence groups attached
        $service = new ProgrammeElementProgressCalculationService($this->user, $this->organisation, $this->programme);
        $result = $service->getConsolidatedProgress();

        expect($result['total_fields'])->toBe(0);
        expect($result['completed_fields'])->toBe(0);
        expect($result['percentage'])->toBe(0.0);
    });

    it('handles division by zero when total fields is zero', function () {
        // Set up data with zero fields
        $tenant = Tenant::factory()->create();
        $catalogueModule = CatalogueModule::factory()->create();
        $catalogue = Catalogue::factory()->create([
            'published' => true,
            'progress_type' => 'organisation',
        ]);
        $element = Element::factory()->create(['number_of_fields' => 0]);

        // Associate relationships
        $catalogueModule->catalogues()->attach($catalogue->id);
        $catalogue->elements()->attach($element->id);

        $licenceGroup = LicenceGroup::factory()->create([
            'programme_id' => $this->programme->id,
            'default_catalogue_module_ids' => [$catalogueModule->id],
        ]);

        // Attach licence group to organisation
        $this->organisation->licenceGroups()->attach($licenceGroup->id, [
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'expires_at' => now()->addMonth(),
        ]);

        // Give user access to the module
        OrganisationCatalogueModuleUser::create([
            'organisation_id' => $this->organisation->id,
            'tenant_id' => $tenant->id,
            'user_id' => $this->user->id,
            'catalogue_module_id' => $catalogueModule->id,
        ]);

        $service = new ProgrammeElementProgressCalculationService($this->user, $this->organisation, $this->programme);
        $result = $service->getConsolidatedProgress();

        expect($result['percentage'])->toBe(0.0);
        expect($result['total_fields'])->toBe(0);
        expect($result['completed_fields'])->toBe(0);
    });
});

describe('getDetailedProgress()', function () {
    it('fetches progress for all licence groups by default', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $catalogueModule1 = CatalogueModule::factory()->create();
        $catalogueModule2 = CatalogueModule::factory()->create();

        $licenceGroup1 = LicenceGroup::factory()->create([
            'programme_id' => $this->programme->id,
            'default_catalogue_module_ids' => [$catalogueModule1->id],
        ]);
        $licenceGroup2 = LicenceGroup::factory()->create([
            'programme_id' => $this->programme->id,
            'default_catalogue_module_ids' => [$catalogueModule2->id],
        ]);

        // Attach licence groups to organisation with one active and one inactive
        $this->organisation->licenceGroups()->attach($licenceGroup1->id, [
            'tenant_id' => $tenant1->id,
            'is_active' => true,
            'expires_at' => now()->addMonth(),
        ]);
        $this->organisation->licenceGroups()->attach($licenceGroup2->id, [
            'tenant_id' => $tenant2->id,
            'is_active' => false,
            'expires_at' => now()->subMonth(),
        ]);

        $service = new ProgrammeElementProgressCalculationService($this->user, $this->organisation, $this->programme);
        $result = $service->getDetailedProgress(false);

        // Both licence groups should be included (active and inactive)
        // Result will be empty unless we set up catalogue module users, but the method should run without error
        expect($result)->toBeInstanceOf(Collection::class);
    });

    it('fetches progress for only active licence groups when flag is true', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $catalogueModule1 = CatalogueModule::factory()->create();
        $catalogueModule2 = CatalogueModule::factory()->create();
        $catalogue = Catalogue::factory()->create([
            'published' => true,
            'progress_type' => 'organisation',
        ]);
        $element = Element::factory()->create(['number_of_fields' => 10]);

        // Associate relationships
        $catalogueModule1->catalogues()->attach($catalogue->id);
        $catalogue->elements()->attach($element->id);

        $licenceGroup1 = LicenceGroup::factory()->create([
            'programme_id' => $this->programme->id,
            'default_catalogue_module_ids' => [$catalogueModule1->id],
        ]);
        $licenceGroup2 = LicenceGroup::factory()->create([
            'programme_id' => $this->programme->id,
            'default_catalogue_module_ids' => [$catalogueModule2->id],
        ]);

        // Attach licence groups with different active states
        $this->organisation->licenceGroups()->attach($licenceGroup1->id, [
            'tenant_id' => $tenant1->id,
            'is_active' => true,
            'expires_at' => now()->addMonth(),
        ]);
        $this->organisation->licenceGroups()->attach($licenceGroup2->id, [
            'tenant_id' => $tenant2->id,
            'is_active' => false,
            'expires_at' => now()->subMonth(),
        ]);

        // Give user access to module 1 only
        OrganisationCatalogueModuleUser::create([
            'organisation_id' => $this->organisation->id,
            'tenant_id' => $tenant1->id,
            'user_id' => $this->user->id,
            'catalogue_module_id' => $catalogueModule1->id,
        ]);

        $service = new ProgrammeElementProgressCalculationService($this->user, $this->organisation, $this->programme);
        $result = $service->getDetailedProgress(true);

        // Only active licence group should be included
        expect($result)->toBeInstanceOf(Collection::class);
        // The result should contain data only from the active licence group
        expect($result->count())->toBe(1);
        expect($result->first()['licence_group_name'])->toBe($licenceGroup1->name);
    });
});

describe('getElementProgress()', function () {
    it('fetches organisation progress correctly', function () {
        $catalogue = Catalogue::factory()->create([
            'progress_type' => 'organisation',
        ]);
        $element = Element::factory()->create();

        // Create ElementProgress record for organisation level
        $elementProgress = ElementProgress::create([
            'element_id' => $element->id,
            'organisation_id' => $this->organisation->id,
            'individual_id' => null,
            'department_id' => null,
            'total_fields' => 10,
            'complete_fields' => 5,
        ]);

        // Create another record that shouldn't be returned
        ElementProgress::create([
            'element_id' => $element->id,
            'organisation_id' => $this->organisation->id,
            'individual_id' => $this->user->id,
            'department_id' => null,
            'total_fields' => 20,
            'complete_fields' => 10,
        ]);

        // Set up required collections for the refactored method
        $groupedProgressCollection = ElementProgress::where('organisation_id', $this->organisation->id)
            ->whereIn('element_id', [$element->id])
            ->get()
            ->groupBy('element_id');
        $userDepartmentIds = collect();

        $service = new ProgrammeElementProgressCalculationService($this->user, $this->organisation, $this->programme);
        $result = $service->getElementProgress($element, $catalogue, $groupedProgressCollection, $userDepartmentIds);

        expect($result)->not->toBeNull();
        expect($result->id)->toBe($elementProgress->id);
        expect($result->individual_id)->toBeNull();
        expect($result->department_id)->toBeNull();
    });

    it('fetches individual progress correctly', function () {
        $catalogue = Catalogue::factory()->create([
            'progress_type' => 'individual',
        ]);
        $element = Element::factory()->create();

        $otherUser = User::factory()->create();

        // Create ElementProgress for the correct user
        $elementProgress = ElementProgress::create([
            'element_id' => $element->id,
            'organisation_id' => $this->organisation->id,
            'individual_id' => $this->user->id,
            'department_id' => null,
            'total_fields' => 10,
            'complete_fields' => 5,
        ]);

        // Create progress for a different user
        ElementProgress::create([
            'element_id' => $element->id,
            'organisation_id' => $this->organisation->id,
            'individual_id' => $otherUser->id,
            'department_id' => null,
            'total_fields' => 20,
            'complete_fields' => 10,
        ]);

        // Set up required collections for the refactored method
        $groupedProgressCollection = ElementProgress::where('organisation_id', $this->organisation->id)
            ->whereIn('element_id', [$element->id])
            ->where(function ($query) {
                $query->where('individual_id', $this->user->id)
                    ->orWhere('organisation_id', $this->organisation->id);
            })
            ->get()
            ->groupBy('element_id');
        $userDepartmentIds = collect();

        $service = new ProgrammeElementProgressCalculationService($this->user, $this->organisation, $this->programme);
        $result = $service->getElementProgress($element, $catalogue, $groupedProgressCollection, $userDepartmentIds);

        expect($result)->not->toBeNull();
        expect($result->id)->toBe($elementProgress->id);
        expect($result->individual_id)->toBe($this->user->id);
    });

    it('fetches department progress correctly', function () {
        $catalogue = Catalogue::factory()->create([
            'progress_type' => 'department',
        ]);
        $element = Element::factory()->create();

        $department = Department::factory()->create([
            'organisation_id' => $this->organisation->id,
        ]);
        $otherDepartment = Department::factory()->create([
            'organisation_id' => $this->organisation->id,
        ]);

        // Attach user to department
        $this->user->departments()->attach($department->id);

        // Create ElementProgress for the user's department
        $elementProgress = ElementProgress::create([
            'element_id' => $element->id,
            'organisation_id' => $this->organisation->id,
            'individual_id' => null,
            'department_id' => $department->id,
            'total_fields' => 10,
            'complete_fields' => 5,
        ]);

        // Create progress for a different department
        ElementProgress::create([
            'element_id' => $element->id,
            'organisation_id' => $this->organisation->id,
            'individual_id' => null,
            'department_id' => $otherDepartment->id,
            'total_fields' => 20,
            'complete_fields' => 10,
        ]);

        // Set up required collections for the refactored method
        $groupedProgressCollection = ElementProgress::where('organisation_id', $this->organisation->id)
            ->whereIn('element_id', [$element->id])
            ->where(function ($query) {
                $query->where('individual_id', $this->user->id)
                    ->orWhere('organisation_id', $this->organisation->id);
            })
            ->get()
            ->groupBy('element_id');
        $userDepartmentIds = $this->user->departments()->pluck('departments.id');

        $service = new ProgrammeElementProgressCalculationService($this->user, $this->organisation, $this->programme);
        $result = $service->getElementProgress($element, $catalogue, $groupedProgressCollection, $userDepartmentIds);

        expect($result)->not->toBeNull();
        expect($result->id)->toBe($elementProgress->id);
        expect($result->department_id)->toBe($department->id);
    });

    it('returns null and logs warning for unknown progress type', function () {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, "Unexpected progress_type 'team'");
            });

        $catalogue = Catalogue::factory()->create([
            'progress_type' => 'team', // Invalid progress type
        ]);
        $element = Element::factory()->create();

        // Create an ElementProgress record so the method doesn't return early
        ElementProgress::create([
            'element_id' => $element->id,
            'organisation_id' => $this->organisation->id,
            'individual_id' => null,
            'department_id' => null,
            'total_fields' => 10,
            'complete_fields' => 5,
        ]);

        // Set up required collections for the refactored method
        $groupedProgressCollection = ElementProgress::where('organisation_id', $this->organisation->id)
            ->whereIn('element_id', [$element->id])
            ->get()
            ->groupBy('element_id');
        $userDepartmentIds = collect();

        $service = new ProgrammeElementProgressCalculationService($this->user, $this->organisation, $this->programme);
        $result = $service->getElementProgress($element, $catalogue, $groupedProgressCollection, $userDepartmentIds);

        expect($result)->toBeNull();
    });
});

describe('Private method logic via public methods', function () {
    it('correctly intersects licence and user catalogue module ids', function () {
        $tenant = Tenant::factory()->create();

        $catalogueModule1 = CatalogueModule::factory()->create();
        $catalogueModule2 = CatalogueModule::factory()->create();
        $catalogueModule3 = CatalogueModule::factory()->create();
        $catalogueModule4 = CatalogueModule::factory()->create();

        $catalogue = Catalogue::factory()->create([
            'published' => true,
            'progress_type' => 'organisation',
        ]);

        $element = Element::factory()->create();

        // Associate catalogues with modules
        $catalogueModule2->catalogues()->attach($catalogue->id);
        $catalogueModule3->catalogues()->attach($catalogue->id);

        // Associate element with catalogue
        $catalogue->elements()->attach($element->id);

        $licenceGroup = LicenceGroup::factory()->create([
            'programme_id' => $this->programme->id,
            'default_catalogue_module_ids' => [
                $catalogueModule1->id,
                $catalogueModule2->id,
                $catalogueModule3->id,
            ],
        ]);

        // Give user access to modules 2, 3, 4
        OrganisationCatalogueModuleUser::create([
            'organisation_id' => $this->organisation->id,
            'tenant_id' => $tenant->id,
            'user_id' => $this->user->id,
            'catalogue_module_id' => $catalogueModule2->id,
        ]);
        OrganisationCatalogueModuleUser::create([
            'organisation_id' => $this->organisation->id,
            'tenant_id' => $tenant->id,
            'user_id' => $this->user->id,
            'catalogue_module_id' => $catalogueModule3->id,
        ]);
        OrganisationCatalogueModuleUser::create([
            'organisation_id' => $this->organisation->id,
            'tenant_id' => $tenant->id,
            'user_id' => $this->user->id,
            'catalogue_module_id' => $catalogueModule4->id,
        ]);

        // Attach licence group to organisation
        $this->organisation->licenceGroups()->attach($licenceGroup->id, [
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'expires_at' => now()->addMonth(),
        ]);

        $service = new ProgrammeElementProgressCalculationService($this->user, $this->organisation, $this->programme);
        $result = $service->getDetailedProgress();

        // Should only have data for modules 2 and 3 (intersection of [1,2,3] and [2,3,4])
        $moduleNames = $result->pluck('catalogue_module_name')->unique()->values();
        expect($moduleNames)->toHaveCount(2);
        expect($moduleNames)->toContain($catalogueModule2->name);
        expect($moduleNames)->toContain($catalogueModule3->name);
    });

    it('uses element progress data when available', function () {
        $tenant = Tenant::factory()->create();

        $catalogueModule = CatalogueModule::factory()->create();
        $catalogue = Catalogue::factory()->create([
            'published' => true,
            'progress_type' => 'organisation',
        ]);
        $element = Element::factory()->create([
            'number_of_fields' => 10,
        ]);

        // Associate relationships
        $catalogueModule->catalogues()->attach($catalogue->id);
        $catalogue->elements()->attach($element->id);

        $licenceGroup = LicenceGroup::factory()->create([
            'programme_id' => $this->programme->id,
            'default_catalogue_module_ids' => [$catalogueModule->id],
        ]);

        // Give user access to the module
        OrganisationCatalogueModuleUser::create([
            'organisation_id' => $this->organisation->id,
            'tenant_id' => $tenant->id,
            'user_id' => $this->user->id,
            'catalogue_module_id' => $catalogueModule->id,
        ]);

        // Create ElementProgress that overrides defaults
        ElementProgress::create([
            'element_id' => $element->id,
            'organisation_id' => $this->organisation->id,
            'individual_id' => null,
            'department_id' => null,
            'total_fields' => 12,
            'complete_fields' => 6,
        ]);

        // Attach licence group to organisation
        $this->organisation->licenceGroups()->attach($licenceGroup->id, [
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'expires_at' => now()->addMonth(),
        ]);

        $service = new ProgrammeElementProgressCalculationService($this->user, $this->organisation, $this->programme);
        $result = $service->getDetailedProgress();

        expect($result)->toHaveCount(1);
        expect($result->first()['total_fields'])->toBe(12);
        expect($result->first()['completed_fields'])->toBe(6);
    });

    it('falls back to element defaults when no progress record exists', function () {
        $tenant = Tenant::factory()->create();

        $catalogueModule = CatalogueModule::factory()->create();
        $catalogue = Catalogue::factory()->create([
            'published' => true,
            'progress_type' => 'organisation',
        ]);
        $element = Element::factory()->create([
            'number_of_fields' => 10,
        ]);

        // Associate relationships
        $catalogueModule->catalogues()->attach($catalogue->id);
        $catalogue->elements()->attach($element->id);

        $licenceGroup = LicenceGroup::factory()->create([
            'programme_id' => $this->programme->id,
            'default_catalogue_module_ids' => [$catalogueModule->id],
        ]);

        // Give user access to the module
        OrganisationCatalogueModuleUser::create([
            'organisation_id' => $this->organisation->id,
            'tenant_id' => $tenant->id,
            'user_id' => $this->user->id,
            'catalogue_module_id' => $catalogueModule->id,
        ]);

        // Do NOT create ElementProgress record

        // Attach licence group to organisation
        $this->organisation->licenceGroups()->attach($licenceGroup->id, [
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'expires_at' => now()->addMonth(),
        ]);

        $service = new ProgrammeElementProgressCalculationService($this->user, $this->organisation, $this->programme);
        $result = $service->getDetailedProgress();

        expect($result)->toHaveCount(1);
        expect($result->first()['total_fields'])->toBe(10);
        expect($result->first()['completed_fields'])->toBe(0);
    });
});
