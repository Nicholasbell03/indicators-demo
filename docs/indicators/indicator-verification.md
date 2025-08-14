## Indicators: Verification Flow

### Audience overview

This document explains how indicator submissions are verified. Non‑developers can read the overview to understand the user journey; technical details follow for engineers maintaining the flow.

-   **What happens when an entrepreneur submits?** If the indicator requires verification, it goes to Level 1 review (and optionally Level 2). If it does not require verification, the task is completed automatically.
-   **Approvals:** Move the submission to the next verification level or complete it if it was the final required approval.
-   **Rejections:** Mark the submission rejected and send the task back for revision.

<MermaidDiagramViewer diagramName="indicator-verification-state" />

---

### How it works (technical)

-   **Service entry points**: `app/Services/Indicators/IndicatorVerificationService.php`
    -   `processSubmissionForVerification(IndicatorSubmission $submission)`
    -   `handleApprovedReview(IndicatorSubmissionReview $review)`
    -   `handleRejectedReview(IndicatorSubmissionReview $review)`
    -   `completeTaskAndSubmission(IndicatorSubmission $submission)`

#### Submission → Level 1 verification

1. `processSubmissionForVerification` loads required relationships on the submission’s `IndicatorTask` (indicatable, organisation, programme, entrepreneur).
2. If the task does not require verification, it is completed immediately by emitting `IndicatorTaskCompleted`.
3. If verification is required, the service ensures an entrepreneur exists and then creates a Level 1 review task via `initiateVerificationForLevel($submission, 1)`.
4. A reviewer is resolved (see Role resolution below). A review task is created with a due date of `now + config('success-compliance-indicators.review_task_days', 7)` days and the `IndicatorSubmissionAwaitingVerification` event is emitted. If no matching user is found for the configured role, the task is created unassigned and the event still fires.

#### Level 2 verification

When a Level 1 review is approved and the indicator has `verifier_2_role_id` configured, `handleApprovedReview` moves the submission to `PENDING_VERIFICATION_2` and creates the Level 2 review task (emits `IndicatorSubmissionAwaitingVerification` for level 2). If no Level 2 is configured, the submission is treated as finally approved and completion is triggered.

#### Rejections

`handleRejectedReview` updates:

-   `IndicatorSubmission.status = REJECTED`
-   `IndicatorTask.status = NEEDS_REVISION`

This allows the entrepreneur to revise and resubmit.

#### Approvals and completion

-   If the current approval is not the final required level, the service advances to the next level.
-   If it is the final required level, the service emits `IndicatorTaskCompleted($submission)` and, when finalizing, `completeTaskAndSubmission` sets:
    -   `IndicatorSubmission.status = APPROVED` (task status is then finalized by the `IndicatorSubmission` observer to ensure ordering).

---

### Events and listeners

Events in `app/Events/Indicator` used by the verification flow:

-   `IndicatorSubmissionSubmitted`
-   `IndicatorSubmissionAwaitingVerification`
-   `IndicatorSubmissionApproved`
-   `IndicatorSubmissionRejected`
-   `IndicatorTaskReadyForSubmission`
-   `IndicatorTaskCompleted`

Listeners in `app/Listeners/Indicator` react to these events (notifications, side‑effects, etc.). The verification service emits `IndicatorSubmissionAwaitingVerification` and `IndicatorTaskCompleted` at the relevant points.

---

### Role configuration and resolution

-   **Where roles are set:** Each indicator (the `indicatable` on `IndicatorTask`) stores the verifier roles:

    -   `verifier_1_role_id` (required when verification is needed)
    -   `verifier_2_role_id` (optional; enables two‑level approval)

-   **When roles are resolved:** At submission time, the service determines the actual user for a given role and context in `findVerifierUser(IndicatorTask $task, ?int $roleId)`. The role is loaded (`Role::find($roleId)`), and the resolver is chosen based on `Role.slug`:
    -   `mentor` → `resolveMentor(Organisation)` from organisation guides/mentors
    -   `programme-manager` → `resolveProgrammeManager(Programme)`
    -   `programme-coordinator` → `resolveProgrammeCoordinator(Programme)`
    -   `regional-coordinator` → `resolveRegionalCoordinator(Organisation.sessionDeliveryLocation)`
    -   `regional-manager` → `resolveRegionalManager(Organisation.sessionDeliveryLocation)`
    -   `eso-manager` → `resolveEsoManager(Organisation|User.primaryTenant → TenantCluster)`

If a resolver cannot find a user, the review task is created without `verifier_user_id`, and the event/note indicates an unassigned task so operational processes can handle assignment.

#### Resolver details by role (exact logic)

-   **Mentor**

    -   **Source**: `Organisation.guides()->isGuide()`
    -   **Resolution**:
        -   If there is exactly one mentor, that user is selected.
        -   If there are zero mentors, no user is resolved. A debug log entry is written with `organisation_id`, `entrepreneur_id`, and `submission_id`.
        -   If there are multiple mentors, no user is resolved (ambiguous). A debug log entry is written with `organisation_id`, `entrepreneur_id`, and `submission_id`.
    -   **Notes**: No fallback to programme or entrepreneur-level associations; ambiguity intentionally results in an unassigned review task.

-   **Programme manager**

    -   **Source**: `Programme.programmeManagers()` (through `programme_user_roles`)
    -   **Selection rule**: The most recently added manager (`programme_user_roles.created_at` descending).
    -   **Not resolved when**: None exist for the programme. A debug log entry is written with `programme_id`.

-   **Programme coordinator**

    -   **Source**: `Programme.programmeCoordinators()` (through `programme_user_roles`)
    -   **Selection rule**: The most recently added coordinator (`programme_user_roles.created_at` descending).
    -   **Not resolved when**: None exist for the programme. A debug log entry is written with `programme_id`.

-   **Regional coordinator**

    -   **Prerequisite**: `Organisation.sessionDeliveryLocation` must be present.
    -   **Source**: `DeliveryLocation.regionalCoordinators()` (through `delivery_location_user_roles`)
    -   **Selection rule**: The most recently added coordinator (`delivery_location_user_roles.created_at` descending).
    -   **Not resolved when**:
        -   The organisation has no delivery location (debug log with `organisation_id`).
        -   The delivery location has no regional coordinators (debug log with `delivery_location_id`).

-   **Regional manager**

    -   **Prerequisite**: `Organisation.sessionDeliveryLocation` must be present.
    -   **Source**: `DeliveryLocation.regionalManagers()` (through `delivery_location_user_roles`)
    -   **Selection rule**: The most recently added manager (`delivery_location_user_roles.created_at` descending).
    -   **Not resolved when**:
        -   The organisation has no delivery location (debug log with `organisation_id`).
        -   The delivery location has no regional managers (debug log with `delivery_location_id`).

-   **ESO manager**
    -   **Tenant selection**: Use `Organisation.getPrimaryTenant()` if available; otherwise fall back to `Entrepreneur.getPrimaryTenant()`.
    -   **Cluster requirement**: The resolved tenant must have a `cluster`.
    -   **Source**: `TenantCluster.esoManagers()` (through `tenant_cluster_user_roles`)
    -   **Selection rule**: The most recently added ESO manager (`tenant_cluster_user_roles.created_at` descending).
    -   **Not resolved when**:
        -   Neither the organisation nor the entrepreneur has a primary tenant (debug log with `entrepreneur_id`, `organisation_id`).
        -   The tenant has no cluster (debug log with `tenant_id`).
        -   The cluster has no ESO managers (debug log with `tenant_cluster_id`).

All of the above resolvers intentionally choose the most recently added related user where multiple may exist, except for Mentors where ambiguity results in no resolution. There is no load‑balancing or round‑robin selection.

---

### Configuration

-   `success-compliance-indicators.review_task_days` (default: 7) controls the review task due date.

---

### Exceptions you may see

The service uses explicit exceptions to enforce required relationships:

-   `MissingIndicatorAssociationException` (e.g., no entrepreneur/organisation/programme)
-   `RoleNotFoundForVerificationLevelException` (no role configured or role not found)
-   `TaskNotFoundForSubmissionException` / `TaskNotFoundForReviewException`
-   `SubmissionNotFoundForReviewException`
-   `IndicatorReviewTaskCreationException`

---

### Entity relationship diagram (developer‑focused)

The following ER diagram shows the key domain entities and how verification tasks relate to submissions, tasks, roles, users, and organisational context used when resolving verifiers.

<MermaidDiagramViewer diagramName="indicator-verification" />

---

### File references

-   Service: `app/Services/Indicators/IndicatorVerificationService.php`
-   Events: `app/Events/Indicator/*`
-   Listeners: `app/Listeners/Indicator/*`
