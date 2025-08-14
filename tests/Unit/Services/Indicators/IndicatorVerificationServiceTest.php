<?php

use App\Enums\IndicatorSubmissionStatusEnum;
use App\Enums\IndicatorTaskStatusEnum;
use App\Enums\UserPermissions;
use App\Events\Indicator\IndicatorSubmissionAwaitingVerification;
use App\Events\Indicator\IndicatorTaskCompleted;
use App\Models\IndicatorReviewTask;
use App\Models\IndicatorSubmission;
use App\Models\IndicatorSubmissionReview;
use App\Models\IndicatorTask;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Programme;
use App\Models\Role;
use App\Models\SessionDeliveryLocation;
use App\Models\TenantCluster;
use App\Models\TenantClusterUserRole;
use App\Models\User;
use App\Services\Indicators\IndicatorVerificationService;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();

    $this->tenant = app('currentTenant');
    $this->organisation = Organisation::factory()->create();
    $this->organisation->tenants()->attach($this->tenant);
    $this->user = User::factory()->create();
    $this->programme = Programme::factory()->create();
    $this->programme->tenants()->attach($this->tenant);

    $this->indicatorTask = IndicatorTask::factory()->create([
        'entrepreneur_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'programme_id' => $this->programme->id,
        'responsible_user_id' => $this->user->id,
        'status' => IndicatorTaskStatusEnum::PENDING,
    ]);

    // Ensure indicator requires verification by default in these tests unless overridden
    $this->indicatorTask->indicatable->update([
        'verifier_1_role_id' => null,
        'verifier_2_role_id' => null,
        'acceptance_value' => null,
    ]);

    $this->service = app(IndicatorVerificationService::class);
});

describe('processSubmissionForVerification', function () {
    it('completes immediately when verification not required and fires completion event', function () {
        // Arrange: indicator requires no verification
        $this->indicatorTask->indicatable->update([
            'verifier_1_role_id' => null,
            'verifier_2_role_id' => null,
        ]);

        /** @var IndicatorSubmission $submission */
        $submission = IndicatorSubmission::factory()->create([
            'indicator_task_id' => $this->indicatorTask->id,
            'status' => IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_1,
        ]);

        // Act
        $this->service->processSubmissionForVerification($submission);

        // Assert
        Event::assertDispatched(IndicatorTaskCompleted::class);
    });

    it('creates a level 1 review task when verification required', function () {
        // Arrange: require L1 verification via indicator
        $verifierRole = Role::factory()->create(['name' => 'mentor']);
        $this->indicatorTask->indicatable->update(['verifier_1_role_id' => $verifierRole->id]);

        /** @var IndicatorSubmission $submission */
        $submission = IndicatorSubmission::factory()->create([
            'indicator_task_id' => $this->indicatorTask->id,
            'status' => IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_1,
        ]);

        // Act
        $this->service->processSubmissionForVerification($submission);

        // Assert
        $task = IndicatorReviewTask::where('indicator_submission_id', $submission->id)->where('verifier_level', 1)->first();
        expect($task)->not->toBeNull();
    });
});

describe('public API idempotency and config behavior', function () {
    it('is idempotent per submission and level via public API', function () {
        $verifierRole = Role::factory()->create(['name' => 'mentor']);
        $this->indicatorTask->indicatable->update(['verifier_1_role_id' => $verifierRole->id]);

        /** @var IndicatorSubmission $submission */
        $submission = IndicatorSubmission::factory()->create([
            'indicator_task_id' => $this->indicatorTask->id,
            'status' => IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_1,
        ]);

        $this->service->processSubmissionForVerification($submission);
        $this->service->processSubmissionForVerification($submission);

        $tasks = IndicatorReviewTask::where('indicator_submission_id', $submission->id)
            ->where('verifier_level', 1)
            ->get();
        expect($tasks->count())->toBe(1);
    });

    it('sets due_date from config days_until_overdue', function () {
        config()->set('success-compliance-indicators.days_until_overdue', 9);

        $verifierRole = Role::factory()->create(['name' => 'mentor']);
        $this->indicatorTask->indicatable->update(['verifier_1_role_id' => $verifierRole->id]);

        /** @var IndicatorSubmission $submission */
        $submission = IndicatorSubmission::factory()->create([
            'indicator_task_id' => $this->indicatorTask->id,
            'status' => IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_1,
        ]);

        $this->service->processSubmissionForVerification($submission);

        $task = IndicatorReviewTask::where('indicator_submission_id', $submission->id)
            ->where('verifier_level', 1)
            ->first();

        expect($task)->not->toBeNull();
        expect($task->due_date->isSameDay(now()->addDays(9)))->toBeTrue();
    });
});

describe('handleApprovedReview', function () {
    it('promotes to level 2 when indicator defines verifier_2_role_id', function () {
        Event::fake([IndicatorSubmissionAwaitingVerification::class]);

        $verifier1Role = Role::factory()->create(['name' => 'mentor']);
        $verifier2Role = Role::factory()->create(['name' => 'Programme Manager']);
        $this->indicatorTask->indicatable->update(['verifier_1_role_id' => $verifier1Role->id, 'verifier_2_role_id' => $verifier2Role->id]);

        /** @var IndicatorSubmission $submission */
        $submission = IndicatorSubmission::factory()->create([
            'indicator_task_id' => $this->indicatorTask->id,
            'status' => IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_1,
        ]);

        $reviewTask = IndicatorReviewTask::factory()->level1()->completed()->forSubmission($submission)->create();
        $review = IndicatorSubmissionReview::factory()->approved()->verifierLevel(1)->create([
            'indicator_review_task_id' => $reviewTask->id,
            'indicator_submission_id' => $reviewTask->indicator_submission_id,
        ]);

        $l2 = IndicatorReviewTask::where('indicator_submission_id', $reviewTask->indicator_submission_id)->where('verifier_level', 2)->first();
        expect($l2)->toBeNull();

        app(IndicatorVerificationService::class)->handleApprovedReview($review);

        $review->submission->refresh();
        expect($review->submission->status)->toBe(IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_2);
        $l2 = IndicatorReviewTask::where('indicator_submission_id', $reviewTask->indicator_submission_id)->where('verifier_level', 2)->first();
        expect($l2)->not->toBeNull();
    });

    it('completes when no level 2 required', function () {
        Event::fake([IndicatorTaskCompleted::class]);

        $verifier1Role = Role::factory()->create(['name' => 'mentor']);
        $this->indicatorTask->indicatable->update(['verifier_1_role_id' => $verifier1Role->id, 'verifier_2_role_id' => null]);

        /** @var IndicatorSubmission $submission */
        $submission = IndicatorSubmission::factory()->create([
            'indicator_task_id' => $this->indicatorTask->id,
            'status' => IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_1,
        ]);

        $reviewTask = IndicatorReviewTask::factory()->level1()->completed()->forSubmission($submission)->create();
        $review = IndicatorSubmissionReview::factory()->approved()->verifierLevel(1)->create([
            'indicator_review_task_id' => $reviewTask->id,
            'indicator_submission_id' => $reviewTask->indicator_submission_id,
        ]);

        app(IndicatorVerificationService::class)->handleApprovedReview($review);

        Event::assertDispatched(IndicatorTaskCompleted::class);
    });
});

describe('handleRejectedReview', function () {
    it('updates statuses on rejection', function () {
        $reviewTask = IndicatorReviewTask::factory()->level1()->completed()->create();
        $review = IndicatorSubmissionReview::factory()->rejected()->verifierLevel(1)->create([
            'indicator_review_task_id' => $reviewTask->id,
            'indicator_submission_id' => $reviewTask->indicator_submission_id,
        ]);

        app(IndicatorVerificationService::class)->handleRejectedReview($review);

        $review->submission->refresh();
        $review->submission->task->refresh();
        expect($review->submission->status)->toBe(IndicatorSubmissionStatusEnum::REJECTED);
        expect($review->submission->task->status)->toBe(IndicatorTaskStatusEnum::NEEDS_REVISION);
    });
});

describe('role resolution when creating review tasks (level 1)', function () {
    it('assigns mentor as verifier when role is mentor', function () {
        // Create role used for verification with slug 'mentor'
        $mentorRole = Role::factory()->create(['name' => 'mentor']);

        // Give role the is-guide permission so the scope `isGuide()` matches
        $perm = Permission::where('name', UserPermissions::IS_GUIDE->value)->first();
        $mentorRole->permissions()->attach($perm->id);

        // Create a mentor user, attach role in current tenant, and link as organisation guide
        $mentorUser = User::factory()->create();
        $mentorUser->roles()->attach($mentorRole->id, ['tenant_id' => $this->tenant->id]);
        $this->organisation->guides()->attach($mentorUser->id);

        // Configure indicator to require L1 mentor verification
        $this->indicatorTask->indicatable->update(['verifier_1_role_id' => $mentorRole->id]);

        /** @var IndicatorSubmission $submission */
        $submission = IndicatorSubmission::factory()->create([
            'indicator_task_id' => $this->indicatorTask->id,
            'status' => IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_1,
        ]);

        app(IndicatorVerificationService::class)->processSubmissionForVerification($submission);

        $task = IndicatorReviewTask::where('indicator_submission_id', $submission->id)
            ->where('verifier_level', 1)
            ->first();

        expect($task)->not->toBeNull();
        expect($task->verifier_user_id)->toBe($mentorUser->id);
    });

    it('assigns programme manager as verifier when role is programme-manager', function () {
        $pmRole = Role::factory()->create(['name' => 'Programme Manager']); // slug => programme-manager
        $pmUser = User::factory()->create();
        $this->programme->assignUserToRole($pmUser->id, \App\Models\ProgrammeUserRole::ROLE_PROGRAMME_MANAGER);

        $this->indicatorTask->indicatable->update(['verifier_1_role_id' => $pmRole->id]);

        /** @var IndicatorSubmission $submission */
        $submission = IndicatorSubmission::factory()->create([
            'indicator_task_id' => $this->indicatorTask->id,
            'status' => IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_1,
        ]);

        app(IndicatorVerificationService::class)->processSubmissionForVerification($submission);

        $task = IndicatorReviewTask::where('indicator_submission_id', $submission->id)
            ->where('verifier_level', 1)
            ->first();

        expect($task)->not->toBeNull();
        expect($task->verifier_user_id)->toBe($pmUser->id);
    });

    it('assigns programme coordinator as verifier when role is programme-coordinator', function () {
        $pcRole = Role::factory()->create(['name' => 'Programme Coordinator']); // slug => programme-coordinator
        $pcUser = User::factory()->create();
        $this->programme->assignUserToRole($pcUser->id, \App\Models\ProgrammeUserRole::ROLE_PROGRAMME_COORDINATOR);

        $this->indicatorTask->indicatable->update(['verifier_1_role_id' => $pcRole->id]);

        /** @var IndicatorSubmission $submission */
        $submission = IndicatorSubmission::factory()->create([
            'indicator_task_id' => $this->indicatorTask->id,
            'status' => IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_1,
        ]);

        app(IndicatorVerificationService::class)->processSubmissionForVerification($submission);

        $task = IndicatorReviewTask::where('indicator_submission_id', $submission->id)
            ->where('verifier_level', 1)
            ->first();

        expect($task)->not->toBeNull();
        expect($task->verifier_user_id)->toBe($pcUser->id);
    });

    it('assigns regional coordinator as verifier when role is regional-coordinator', function () {
        $rcRole = Role::factory()->create(['name' => 'regional-coordinator']);
        $rcUser = User::factory()->create();
        $deliveryLocation = SessionDeliveryLocation::factory()->create();
        $this->organisation->update(['session_delivery_location_id' => $deliveryLocation->id]);
        $deliveryLocation->assignUserToRole($rcUser->id, \App\Models\DeliveryLocationUserRole::ROLE_REGIONAL_COORDINATOR);

        $this->indicatorTask->indicatable->update(['verifier_1_role_id' => $rcRole->id]);

        /** @var IndicatorSubmission $submission */
        $submission = IndicatorSubmission::factory()->create([
            'indicator_task_id' => $this->indicatorTask->id,
            'status' => IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_1,
        ]);

        app(IndicatorVerificationService::class)->processSubmissionForVerification($submission);

        $task = IndicatorReviewTask::where('indicator_submission_id', $submission->id)
            ->where('verifier_level', 1)
            ->first();

        expect($task)->not->toBeNull();
        expect($task->verifier_user_id)->toBe($rcUser->id);
    });

    it('assigns regional manager as verifier when role is regional-manager', function () {
        $rmRole = Role::factory()->create(['name' => 'regional-manager']);
        $rmUser = User::factory()->create();
        $deliveryLocation = SessionDeliveryLocation::factory()->create();
        $this->organisation->update(['session_delivery_location_id' => $deliveryLocation->id]);
        $deliveryLocation->assignUserToRole($rmUser->id, \App\Models\DeliveryLocationUserRole::ROLE_REGIONAL_MANAGER);

        $this->indicatorTask->indicatable->update(['verifier_1_role_id' => $rmRole->id]);

        /** @var IndicatorSubmission $submission */
        $submission = IndicatorSubmission::factory()->create([
            'indicator_task_id' => $this->indicatorTask->id,
            'status' => IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_1,
        ]);

        app(IndicatorVerificationService::class)->processSubmissionForVerification($submission);

        $task = IndicatorReviewTask::where('indicator_submission_id', $submission->id)
            ->where('verifier_level', 1)
            ->first();

        expect($task)->not->toBeNull();
        expect($task->verifier_user_id)->toBe($rmUser->id);
    });

    it('assigns ESO manager as verifier when role is eso-manager', function () {
        $esoRole = Role::factory()->create(['name' => 'eso-manager']);
        $esoUser = User::factory()->create();
        $cluster = TenantCluster::factory()->create();
        // Link test tenant to cluster
        $this->tenant->tenant_cluster_id = $cluster->id;
        $this->tenant->save();
        // Mark user as ESO manager for cluster
        $cluster->assignUserToRole($esoUser->id, TenantClusterUserRole::ROLE_ESO_MANAGER);

        $this->indicatorTask->indicatable->update(['verifier_1_role_id' => $esoRole->id]);

        /** @var IndicatorSubmission $submission */
        $submission = IndicatorSubmission::factory()->create([
            'indicator_task_id' => $this->indicatorTask->id,
            'status' => IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_1,
        ]);

        app(IndicatorVerificationService::class)->processSubmissionForVerification($submission);

        $task = IndicatorReviewTask::where('indicator_submission_id', $submission->id)
            ->where('verifier_level', 1)
            ->first();

        expect($task)->not->toBeNull();
        expect($task->verifier_user_id)->toBe($esoUser->id);
    });
});

describe('initiateVerificationForLevel events', function () {
    it('dispatches IndicatorSubmissionAwaitingVerification when a verifier is resolved', function () {
        Event::fake([IndicatorSubmissionAwaitingVerification::class]);

        // Setup: mentor role with guide permission and a user who is a guide for the organisation
        $mentorRole = Role::factory()->create(['name' => 'mentor']);
        $perm = Permission::where('name', UserPermissions::IS_GUIDE->value)->first();
        $mentorRole->permissions()->attach($perm->id);

        $mentorUser = User::factory()->create();
        $mentorUser->roles()->attach($mentorRole->id, ['tenant_id' => $this->tenant->id]);
        $this->organisation->guides()->attach($mentorUser->id);

        // Require level 1 verification with mentor role
        $this->indicatorTask->indicatable->update(['verifier_1_role_id' => $mentorRole->id]);

        /** @var IndicatorSubmission $submission */
        $submission = IndicatorSubmission::factory()->create([
            'indicator_task_id' => $this->indicatorTask->id,
            'status' => IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_1,
        ]);

        // Invoke protected method via reflection
        $service = app(IndicatorVerificationService::class);
        $method = new \ReflectionMethod(IndicatorVerificationService::class, 'initiateVerificationForLevel');
        $method->setAccessible(true);
        $method->invoke($service, $submission, 1);

        Event::assertDispatched(IndicatorSubmissionAwaitingVerification::class);
    });

    it('does not dispatch IndicatorSubmissionAwaitingVerification when no verifier is resolved', function () {
        Event::fake([IndicatorSubmissionAwaitingVerification::class]);

        // Setup: mentor role present but no guide assigned to organisation
        $mentorRole = Role::factory()->create(['name' => 'mentor']);

        // Require level 1 verification with mentor role
        $this->indicatorTask->indicatable->update(['verifier_1_role_id' => $mentorRole->id]);

        /** @var IndicatorSubmission $submission */
        $submission = IndicatorSubmission::factory()->create([
            'indicator_task_id' => $this->indicatorTask->id,
            'status' => IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_1,
        ]);

        // Invoke protected method via reflection
        $service = app(IndicatorVerificationService::class);
        $method = new \ReflectionMethod(IndicatorVerificationService::class, 'initiateVerificationForLevel');
        $method->setAccessible(true);
        $method->invoke($service, $submission, 1);

        Event::assertNotDispatched(IndicatorSubmissionAwaitingVerification::class);
    });
});
