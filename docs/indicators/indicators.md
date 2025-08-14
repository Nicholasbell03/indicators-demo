# Indicators

## Intro

The Indicators feature provides a comprehensive two-part workflow for managing indicator submissions and verifications within the Filament admin panel. This feature allows administrators to submit indicator-related tasks and enables verifiers to review, approve, or reject these submissions through a structured multi-level verification process.

The system supports both Success Indicators and Compliance Indicators, with conditional file upload requirements and a robust event-driven workflow that ensures proper task progression and status management.

## Architecture Overview

The Indicators feature is built around a **Filament Cluster** approach with two main resources:

1. **Admin Indicator Submissions**: Where administrators submit their assigned indicator tasks
2. **Indicator Verifications**: Where verifiers review and approve/reject submissions

### Core Models

-   **IndicatorTask**: Represents tasks assigned to administrators for indicator completion
-   **IndicatorSubmission**: Contains the actual submission data (values, files, comments)
-   **IndicatorReviewTask**: Represents verification tasks assigned to verifiers
-   **IndicatorSubmissionReview**: Records the verification decisions and feedback

### Event-Driven Workflow

The system uses Laravel events and listeners to manage the workflow progression:

-   `IndicatorSubmissionSubmitted` → Creates Level 1 verification task
-   `IndicatorSubmissionApproved` → Either creates Level 2 task or completes workflow
-   `IndicatorSubmissionRejected` → Returns task to submitter with feedback

## Workflow Process

### 1. Task Assignment

-   Administrators are assigned IndicatorTasks through existing mechanisms
-   Tasks appear in the Indicators cluster when users have assigned tasks
-   Tasks are scoped by user (not tenant) for proper visibility control

### 2. Submission Process

-   Admin opens submission modal from their task list
-   Form dynamically populates based on indicator type and requirements
-   File uploads are conditionally required based on `supporting_documentation` field
-   Comments and values are captured along with file attachments
-   Submission triggers `IndicatorSubmissionSubmitted` event

### 3. Verification Process

-   Level 1 verifiers receive tasks automatically via event listeners
-   Verifiers can approve (proceeds to Level 2) or reject (returns to admin)
-   Level 2 verification follows same pattern
-   Final approval completes the entire workflow

### 4. Status Management

-   Tasks progress through: Pending → Submitted → In Verification → Complete
-   Status display uses separate `displayLabel()` for UI and `displayStatus()` for logic
-   Failed submissions return to "Needs Revision" status with previous data pre-filled

## Implementation Details

### Filament Cluster Structure

```php
// app/Filament/Admin/Clusters/Indicators.php
class Indicators extends Cluster
{
    protected static ?string $navigationGroup = 'Manage';

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user->indicatorTasks()->exists() ||
               $user->indicatorReviewTasks()->exists();
    }
}
```

### User-Scoped Queries

Unlike most Filament resources, Indicators use **user-based scoping** instead of tenant scoping:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->where('responsible_user_id', auth()->id())
        ->with(['indicator', 'entrepreneur', 'organisation', 'programme']);
}
```

### Service Layer Integration

The `IndicatorSubmissionService` was extended with Filament adapter methods:

```php
// Service adapter for Filament compatibility
public function createSubmissionFromFilament(array $data, IndicatorTask $task): IndicatorSubmission
{
    $transformedData = $this->transformFilamentData($data, $task);
    return $this->createSubmission($transformedData, $task);
}

private function transformFilamentData(array $data, IndicatorTask $task): array
{
    // Transforms Filament form data to service-expected format
    // Handles file upload path conversions
    // Maintains backward compatibility
}
```

### File Upload Handling

File uploads are converted from Filament's storage paths to proper `UploadedFile` instances:

```php
private function transformFilamentAttachments(?array $attachments): array
{
    if (empty($attachments)) return [];

    return collect($attachments)->map(function ($path) {
        // Handle various Filament path formats
        $storagePath = str_replace(['storage/app/public/', 'public/'], '', $path);
        return new UploadedFile(
            storage_path("app/public/{$storagePath}"),
            basename($storagePath),
            Storage::disk('public')->mimeType($storagePath),
            null,
            true
        );
    })->toArray();
}
```

### Event Listeners Integration

Event listeners handle workflow progression:

```php
// IndicatorSubmissionSubmittedListener
public function handle(IndicatorSubmissionSubmitted $event): void
{
    $submission = $event->submission;
    $verifier1Role = Role::where('name', 'verifier_1_role_id')->first();

    if ($verifier1Role && $verifier1Role->users()->exists()) {
        $verifier = $verifier1Role->users()->first();
        IndicatorReviewTask::create([
            'indicator_submission_id' => $submission->id,
            'verifier_user_id' => $verifier->id,
            'verification_level' => 1,
            'status' => IndicatorReviewTaskStatusEnum::PENDING,
        ]);
        $submission->task->update(['status' => IndicatorTaskStatusEnum::SUBMITTED]);
    } else {
        // No verifier available - mark as complete
        $submission->task->update(['status' => IndicatorTaskStatusEnum::COMPLETED]);
    }
}
```

## User Interface

### Tabbed Interface

Both resources use tabbed interfaces for organization:

**Admin Submissions**:

-   **Pending**: Shows Pending, Needs Revision, and Overdue tasks
-   **In Verification**: Shows Submitted tasks
-   **Complete**: Shows Completed tasks

**Verifications**:

-   **Pending**: Active verification tasks
-   **Completed**: Finished verifications

### Dynamic Modal System

Modals adapt based on context:

-   **Submit Mode**: Interactive form for pending/revision tasks
-   **View Mode**: Read-only display for submitted/complete tasks
-   **Verify Mode**: Approval/rejection interface for verifiers

### Form Field Behavior

Forms are dynamically generated based on indicator data:

-   Title from `indicator.title`
-   Helper text from `additional_instruction`
-   File uploads conditional on `supporting_documentation`
-   Pre-filled data for revision tasks

## Permission and Access Control

### Cluster Visibility

The Indicators cluster only appears for users with relevant tasks:

```php
public static function shouldRegisterNavigation(): bool
{
    $user = auth()->user();
    return $user->indicatorTasks()->exists() ||
           $user->indicatorReviewTasks()->exists();
}
```

### Filament Panel Requirements

**App Panel Access** requires:

-   User must be admin: `$user->isAdmin()`
-   Current tenant must be landlord: `$tenant->isLandlord()`
-   OR user has `MANAGE_SESSIONS_SCHEDULER` permission

**Admin Panel Access** requires:

-   User has `VIEW_ADMIN_DASHBOARD` permission
-   Admin role assignment

### Data Scoping

-   **User-scoped**: Only assigned tasks visible to each user
-   **Non-tenant scoped**: Works across tenant boundaries
-   **Relationship eager loading**: Prevents N+1 queries

## Testing Considerations

### Unit Test Coverage

The feature includes comprehensive test coverage:

-   **Service Tests**: `IndicatorSubmissionServiceTest.php`
-   **Event Listener Tests**: `IndicatorSubmissionApprovedListenerTest.php`, `IndicatorSubmissionRejectedListenerTest.php`
-   **Filament Resource Tests**: `IndicatorsClusterTest.php`, `IndicatorSubmissionsResourceTest.php`, `IndicatorVerificationsResourceTest.php`

### Parallel Test Isolation

Key considerations for parallel test execution:

1. **Tenant Context**: Tests require proper landlord tenant setup
2. **Role/Permission Creation**: Use `firstOrCreate()` to prevent conflicts
3. **Database Constraints**: Handle foreign key and unique constraints properly
4. **Event Dispatching**: Remove `ShouldDispatchAfterCommit` for immediate testing

### Test Setup Pattern

```php
beforeEach(function () {
    // Create tenant context for Filament panel access
    $this->tenant = Tenant::firstOrCreate(
        ['id' => config('multitenancy.landlord_id')],
        ['id' => config('multitenancy.landlord_id'), 'name' => 'Landlord Tenant']
    );
    app()->instance('currentTenant', $this->tenant);

    // Create admin with proper permissions
    $this->admin = User::factory()->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin'], [...]);
    $this->admin->roles()->attach($adminRole, ['tenant_id' => config('multitenancy.landlord_id')]);

    $this->actingAs($this->admin);
});
```

## Performance Considerations

### Query Optimization

-   Eager loading relationships: `->with(['indicator', 'entrepreneur', 'organisation'])`
-   User-scoped queries prevent large dataset issues
-   Pagination implemented for table views

### File Storage

-   **Production**: Files stored in S3 via `indicator_submissions` disk (`s3://bucket/indicator_submissions/`)
-   **Testing**: Files stored locally via `indicator_submissions_local` disk (`storage/app/public/indicator_submissions/`)
-   Proper MIME type detection and validation
-   Secure file access through Laravel's storage system

### Caching

-   No explicit caching implemented (relies on Laravel's query cache)
-   Consider adding model-level caching for frequently accessed data

## Maintenance Notes

### Important Considerations for Future Developers

1. **Service Layer**: The `IndicatorSubmissionService` maintains dual compatibility - both controller and Filament usage. Don't break this pattern.

2. **Event System**: Events are crucial for workflow progression. Always test event dispatching when making changes.

3. **Status Management**: The dual-status system (`displayLabel` vs `displayStatus`) serves different purposes - don't merge them.

4. **File Handling**: Filament file uploads require transformation to `UploadedFile` objects. This is handled in the service adapter.

5. **User Scoping**: This feature intentionally uses user-based scoping instead of tenant scoping. Don't convert to tenant scoping.

### Common Pitfalls

-   **Missing Tenant Context**: Filament panels require proper tenant setup in tests
-   **Event Timing**: Be careful with event dispatching timing - some tests need immediate dispatch
-   **File Path Handling**: Filament provides various path formats that need normalization
-   **Permission Stacking**: Admin users need both role assignment AND specific permissions

### Extension Points

The feature is designed for extension:

-   Additional indicator types can be added easily
-   Verification levels can be increased beyond 2
-   Additional file types and validation rules can be implemented
-   Custom business logic can be added to event listeners

## Configuration

### Required Configuration

```php
// config/multitenancy.php
'landlord_id' => 1,

// Required roles (created via seeder or migration)
- 'admin' role with appropriate permissions
- 'verifier_1_role_id' role for Level 1 verifiers
- 'verifier_2_role_id' role for Level 2 verifiers

// Required permissions
- VIEW_ADMIN_DASHBOARD
- MANAGE_SESSIONS_SCHEDULER (for app panel alternative access)

// File storage disks (see Storage Configuration section)
- 'indicator_submissions' (S3 for production)
- 'indicator_submissions_local' (local for testing/development)
- Environment-aware disk selection via IndicatorSubmissionAttachment::getDisk()
```

### Storage Configuration

```php
// config/filesystems.php

// Production S3 storage (default)
'indicator_submissions' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'visibility' => 'public',
    'root' => 'indicator_submissions/',
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
],

// Local storage (testing/development)
'indicator_submissions_local' => [
    'driver' => 'local',
    'root' => storage_path('app/public/indicator_submissions'),
    'url' => env('APP_URL').'/storage/indicator_submissions',
    'visibility' => 'public',
],
```

**Environment-Aware Configuration**: The system automatically uses `indicator_submissions_local` disk in testing environments and `indicator_submissions` (S3) disk in production, consistent with other file storage throughout the application.

This comprehensive documentation should provide future developers with everything needed to understand, maintain, and extend the Indicators feature.
