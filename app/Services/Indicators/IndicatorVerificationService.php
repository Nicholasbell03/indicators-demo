<?php

declare(strict_types=1);

namespace App\Services\Indicators;

use App\Enums\IndicatorSubmissionStatusEnum;
use App\Events\Indicator\IndicatorSubmissionAwaitingVerification;
use App\Events\Indicator\IndicatorTaskCompleted;
use App\Exceptions\IndicatorReviewTaskCreationException;
use App\Exceptions\MissingIndicatorAssociationException;
use App\Exceptions\RoleNotFoundForVerificationLevelException;
use App\Exceptions\SubmissionNotFoundForReviewException;
use App\Exceptions\TaskNotFoundForReviewException;
use App\Exceptions\TaskNotFoundForSubmissionException;
use App\Models\IndicatorReviewTask;
use App\Models\IndicatorSubmission;
use App\Models\IndicatorSubmissionReview;
use App\Models\IndicatorTask;
use App\Models\Organisation;
use App\Models\Programme;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLoggerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class IndicatorVerificationService
{
    public function __construct(private ActivityLoggerService $logger) {}

    /**
     * Process a new submission, either completing it or creating the first review task.
     */
    public function processSubmissionForVerification(IndicatorSubmission $submission): void
    {
        $submission->loadMissing('task.indicatable', 'task.organisation', 'task.programme', 'task.entrepreneur');

        $task = $this->getTaskFromSubmission($submission);

        // If the task doesn't require verification, complete it and stop.
        if ($this->completeTaskIfNoVerificationIsRequired($task, $submission)) {
            return;
        }

        // Verification is required, so ensure an entrepreneur is present.
        $this->ensureEntrepreneurIsPresent($task);

        // Submissions that require verification are always sent to level 1 for verification first.
        $this->initiateVerificationForLevel($submission, 1);
    }

    /**
     * Handle an approved review, either advancing to the next level or completing the task.
     */
    public function handleApprovedReview(IndicatorSubmissionReview $review): void
    {
        $task = $this->getTaskFromReview($review);
        $submission = $this->getSubmissionFromReview($review);
        $submission->loadMissing('task.indicatable'); // Ensure indicatable is loaded for the check below

        $isLevelOneApproval = (int) $review->verifier_level === 1;
        $requiresLevelTwo = ! is_null($task->indicatable?->verifier_2_role_id);

        if ($isLevelOneApproval && $requiresLevelTwo) {
            $this->advanceToLevelTwoVerification($submission);
        } else {
            // This was the final required approval.
            event(new IndicatorTaskCompleted($submission));
        }
    }

    /**
     * Handle a rejected review by updating the submission and task statuses.
     */
    public function handleRejectedReview(IndicatorSubmissionReview $review): void
    {
        $submission = $this->getSubmissionFromReview($review);

        $submission->status = IndicatorSubmissionStatusEnum::REJECTED;
        $submission->save();
        // Task updates are handled by the IndicatorSubmissionObserver to ensure this always takes place after the submission is rejected
    }

    public function completeTaskAndSubmission(IndicatorSubmission $submission): bool
    {
        return DB::transaction(function () use ($submission) {
            // Ensure latest state and make operation idempotent to handle concurrent events
            $submission->refresh();
            if ($submission->status === IndicatorSubmissionStatusEnum::APPROVED) {
                return false;
            }

            $submission->status = IndicatorSubmissionStatusEnum::APPROVED;
            $submission->save();

            // Task updates are handled by the IndicatorSubmissionObserver to ensure this always takes place after the submission is approved
            return true;
        });
    }

    /**
     * Creates a review task for the specified verification level.
     */
    protected function initiateVerificationForLevel(IndicatorSubmission $submission, int $level): void
    {
        $task = $this->getTaskFromSubmission($submission);

        $verifierRoleId = $this->getRoleIdForVerificationLevel($task, $level);
        if (! $verifierRoleId) {
            throw new RoleNotFoundForVerificationLevelException("No verifier role ID found for verification level {$level} for submission {$submission->id}");
        }

        $verifier = $this->findVerifierUser($submission, $task, $verifierRoleId);

        DB::transaction(function () use ($submission, $level, $verifier, $verifierRoleId) {
            $this->createReviewTask($submission, $level, $verifierRoleId, $verifier);

            if ($verifier) {
                event(new IndicatorSubmissionAwaitingVerification($submission, $level, $verifier));
            }
        });
    }

    /**
     * Checks if a task requires verification. If not, it completes the task and returns true.
     *
     * @return bool True if the task was completed, false otherwise.
     */
    private function completeTaskIfNoVerificationIsRequired(IndicatorTask $task, IndicatorSubmission $submission): bool
    {
        if ($task->requiresVerification()) {
            return false;
        }

        Log::debug('No verification needed for submission; completing task.', [
            'indicator_submission_id' => $submission->id,
            'indicator_task_id' => $task->id,
            'indicator_title' => $task->indicatable?->title,
        ]);

        if (! $task->isCompleted()) {
            event(new IndicatorTaskCompleted($submission));
        }

        return true;
    }

    /**
     * Updates submission status and initiates level 2 verification.
     */
    private function advanceToLevelTwoVerification(IndicatorSubmission $submission): void
    {
        $submission->status = IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_2;
        $submission->save();

        $this->initiateVerificationForLevel($submission, 2);
    }

    /**
     * Retrieves the associated submission from a review, ensuring it exists.
     *
     * @throws SubmissionNotFoundForReviewException
     */
    private function getSubmissionFromReview(IndicatorSubmissionReview $review): IndicatorSubmission
    {
        if (! $review->submission) {
            throw new SubmissionNotFoundForReviewException("Indicator Submission not found for IndicatorSubmissionReview ID: {$review->id}");
        }

        return $review->submission;
    }

    /**
     * Retrieves the associated task from a submission, ensuring it exists.
     *
     * @throws TaskNotFoundForSubmissionException
     */
    private function getTaskFromSubmission(IndicatorSubmission $submission): IndicatorTask
    {
        if (! $submission->task) {
            throw new TaskNotFoundForSubmissionException("Indicator Task not found for IndicatorSubmission ID: {$submission->id}");
        }

        return $submission->task;
    }

    /**
     * Retrieves the associated task from a review, ensuring all relationships exist.
     *
     * @throws TaskNotFoundForReviewException
     */
    private function getTaskFromReview(IndicatorSubmissionReview $review): IndicatorTask
    {
        if (! $review->submission) {
            throw new TaskNotFoundForReviewException("IndicatorTask not found for IndicatorSubmissionReview ID: {$review->id}");
        }

        return $this->getTaskFromSubmission($review->submission);
    }

    /**
     * Ensures a submission has an associated entrepreneur.
     *
     * @throws MissingIndicatorAssociationException
     */
    private function ensureEntrepreneurIsPresent(IndicatorTask $task): void
    {
        if (! $task->entrepreneur) {
            throw new MissingIndicatorAssociationException("Task {$task->id} has no associated entrepreneur, which is required for verification.");
        }
    }

    private function createReviewTask(IndicatorSubmission $submission, int $verificationLevel, ?int $verifierRoleId, ?User $verifier = null): IndicatorReviewTask
    {
        $daysUntilOverdue = (int) config('success-compliance-indicators.review_task_days', 7);
        $dueDate = now()->addDays($daysUntilOverdue)->format('Y-m-d');

        try {
            $reviewTask = IndicatorReviewTask::firstOrCreate([
                'indicator_submission_id' => $submission->id,
                'verifier_level' => $verificationLevel,
            ], [
                'indicator_task_id' => $submission->indicator_task_id,
                'verifier_user_id' => $verifier?->id,
                'verifier_role_id' => $verifierRoleId,
                'due_date' => $dueDate,
            ]);

            if ($reviewTask->wasRecentlyCreated) {
                $this->logReviewTaskCreated($reviewTask);
            }

            return $reviewTask;
        } catch (\Throwable $e) {
            throw new IndicatorReviewTaskCreationException('Error creating review task: '.$e->getMessage(), previous: $e);
        }
    }

    private function getRoleIdForVerificationLevel(IndicatorTask $task, int $verificationLevel): ?int
    {
        $indicator = $task->indicatable;
        if (! $indicator) {
            throw new MissingIndicatorAssociationException('Indicator task has no associated indicatable');
        }

        return $verificationLevel === 1
            ? ($indicator->verifier_1_role_id ?? null)
            : ($indicator->verifier_2_role_id ?? null);
    }

    private function findVerifierUser(IndicatorSubmission $submission, IndicatorTask $task, ?int $roleId): ?User
    {
        $entrepreneur = $task->entrepreneur;
        $organisation = $task->organisation;
        $programme = $task->programme;

        if (! $entrepreneur) {
            throw new MissingIndicatorAssociationException('Indicator task has no associated entrepreneur');
        }

        if (! $organisation) {
            throw new MissingIndicatorAssociationException('Indicator task has no associated organisation');
        }

        if (! $programme) {
            throw new MissingIndicatorAssociationException('Indicator task has no associated programme');
        }

        $role = $roleId ? Role::find($roleId) : null;
        if (! $role) {
            throw new RoleNotFoundForVerificationLevelException('Role not found');
        }

        return match ($role->slug) {
            'mentor' => $this->resolveMentor($organisation, $submission, $entrepreneur),
            'programme-manager' => $this->resolveProgrammeManager($programme),
            'programme-coordinator' => $this->resolveProgrammeCoordinator($programme),
            'regional-coordinator' => $this->resolveRegionalCoordinator($organisation),
            'regional-manager' => $this->resolveRegionalManager($organisation),
            'eso-manager' => $this->resolveEsoManager($organisation, $entrepreneur),
            default => throw new RoleNotFoundForVerificationLevelException('No mapping exists for this role : '.$role->slug),
        };
    }

    private function logReviewTaskCreated(IndicatorReviewTask $reviewTask): void
    {
        $this->logger->logActivity(
            description: 'Indicator review task created',
            subject: $reviewTask,
            causer: $reviewTask->submission?->entrepreneur,
            properties: [
                'entrepreneur_id' => $reviewTask->submission?->entrepreneur_id,
                'indicator_submission_id' => $reviewTask->indicator_submission_id,
                'indicator_task_id' => $reviewTask->indicator_task_id,
                'indicator_type' => $reviewTask->task?->indicatable_type,
                'indicator_id' => $reviewTask->task?->indicatable_id,
                'indicator_title' => $reviewTask->task?->indicatable?->title,
                'verifier_level' => $reviewTask->verifier_level,
                'verifier_user_id' => $reviewTask->verifier_user_id,
                'verifier_role_id' => $reviewTask->verifier_role_id,
                'due_date' => $reviewTask->due_date,
                'days_until_overdue' => config('success-compliance-indicators.review_task_days', 7),
                'reason' => $reviewTask->verifier_user_id ? 'Assigned resolved verifier' : 'No verifier found; created unassigned task',
            ]
        );
    }

    /**
     * Resolve the mentor who should verify for the given entrepreneur.
     */
    protected function resolveMentor(Organisation $organisation, IndicatorSubmission $submission, User $entrepreneur): ?User
    {
        // Get all mentors for the organisation
        $mentors = $organisation->guides()
            ->isGuide()
            ->get();

        if ($mentors->count() > 1) {
            Log::debug('Multiple mentors found for organisation. Cannot resolve mentor for indicator verification', [
                'organisation_id' => $organisation->id,
                'entrepreneur_id' => $entrepreneur->id,
                'submission_id' => $submission->id,
            ]);

            return null;
        }

        if ($mentors->isEmpty()) {
            Log::debug('No mentors exist for organisation. Cannot resolve mentor for indicator verification', [
                'organisation_id' => $organisation->id,
                'entrepreneur_id' => $entrepreneur->id,
                'submission_id' => $submission->id,
            ]);

            return null;
        }

        return $mentors->first();
    }

    /**
     * Resolve the programme manager for the entrepreneur within the relevant programme context.
     */
    protected function resolveProgrammeManager(Programme $programme): ?User
    {
        // Get the most recently added programme manager
        $programmeManager = $programme->programmeManagers()
            ->orderByDesc('programme_user_roles.created_at')
            ->first();

        if (! $programmeManager) {
            Log::debug('No programme manager found for programme. Cannot resolve programme manager for indicator verification', [
                'programme_id' => $programme->id,
            ]);

            return null;
        }

        return $programmeManager;
    }

    /**
     * Resolve the programme coordinator for the entrepreneur within the relevant programme context.
     */
    protected function resolveProgrammeCoordinator(Programme $programme): ?User
    {
        // Get the most recently added programme coordinator
        $programmeCoordinator = $programme->programmeCoordinators()
            ->orderByDesc('programme_user_roles.created_at')
            ->first();

        if (! $programmeCoordinator) {
            Log::debug('No programme coordinator found for programme. Cannot resolve programme coordinator for indicator verification', [
                'programme_id' => $programme->id,
            ]);

            return null;
        }

        return $programmeCoordinator;
    }

    /**
     * Resolve the regional coordinator for the entrepreneur's region/delivery location.
     */
    protected function resolveRegionalCoordinator(Organisation $organisation): ?User
    {
        $deliveryLocation = $organisation->sessionDeliveryLocation;

        if (! $deliveryLocation) {
            Log::debug('No delivery location found for organisation. Cannot resolve regional coordinator for indicator verification', [
                'organisation_id' => $organisation->id,
            ]);

            return null;
        }

        // Get the most recently added regional coordinator
        $regionalCoordinator = $deliveryLocation->regionalCoordinators()
            ->orderByDesc('delivery_location_user_roles.created_at')
            ->first();

        if (! $regionalCoordinator) {
            Log::debug('No regional coordinator found for delivery location. Cannot resolve regional coordinator for indicator verification', [
                'delivery_location_id' => $deliveryLocation->id,
            ]);

            return null;
        }

        return $regionalCoordinator;
    }

    /**
     * Resolve the regional manager for the entrepreneur's region/delivery location.
     */
    protected function resolveRegionalManager(Organisation $organisation): ?User
    {
        $deliveryLocation = $organisation->sessionDeliveryLocation;

        if (! $deliveryLocation) {
            Log::debug('No delivery location found for organisation. Cannot resolve regional manager for indicator verification', [
                'organisation_id' => $organisation->id,
            ]);

            return null;
        }

        // Get the most recently added regional manager
        $regionalManager = $deliveryLocation->regionalManagers()
            ->orderByDesc('delivery_location_user_roles.created_at')
            ->first();

        if (! $regionalManager) {
            Log::debug('No regional manager found for delivery location. Cannot resolve regional manager for indicator verification', [
                'delivery_location_id' => $deliveryLocation->id,
            ]);

            return null;
        }

        return $regionalManager;
    }

    /**
     * Resolve the ESO manager according to business rules (global or org-scoped).
     */
    protected function resolveEsoManager(Organisation $organisation, User $entrepreneur): ?User
    {
        $organisationPrimaryTenant = $organisation->getPrimaryTenant();
        $entrepreneurPrimaryTenant = $entrepreneur->getPrimaryTenant();
        // Prefer the organisation primary tenant, if it exists, otherwise use the entrepreneur primary tenant
        $tenant = $organisationPrimaryTenant ?? $entrepreneurPrimaryTenant;

        // If no tenant is found, return null
        if (! $tenant) {
            Log::debug('No primarytenant found for entrepreneur or organisation. Cannot resolve ESO manager for indicator verification', [
                'entrepreneur_id' => $entrepreneur->id,
                'organisation_id' => $organisation->id,
            ]);

            return null;
        }

        $tenantCluster = $tenant->cluster;

        if (! $tenantCluster) {
            Log::debug('No tenant cluster found for tenant. Cannot resolve ESO manager for indicator verification', [
                'tenant_id' => $tenant->id,
            ]);

            return null;
        }

        // Get the most recently added ESO manager
        $esoManager = $tenantCluster->esoManagers()
            ->orderByDesc('tenant_cluster_user_roles.created_at')
            ->first();

        if (! $esoManager) {
            Log::debug('No ESO manager found for tenant cluster. Cannot resolve ESO manager for indicator verification', [
                'tenant_cluster_id' => $tenantCluster->id,
            ]);

            return null;
        }

        return $esoManager;
    }
}
