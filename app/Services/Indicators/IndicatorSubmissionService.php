<?php

declare(strict_types=1);

namespace App\Services\Indicators;

use App\Enums\IndicatorResponseFormatEnum;
use App\Enums\IndicatorSubmissionStatusEnum;
use App\Events\Indicator\IndicatorSubmissionSubmitted;
use App\Models\IndicatorSubmission;
use App\Models\IndicatorSubmissionAttachment;
use App\Models\IndicatorTask;
use App\Models\OrganisationProgrammeSeat;
use App\Models\User;
use App\Uploaders\FileUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class IndicatorSubmissionService
{
    public function createSubmission(array $data, User $submitter): IndicatorSubmission
    {
        $submission = DB::transaction(function () use ($data, $submitter) {
            $indicatorTask = IndicatorTask::findOrFail($data['indicator_task_id'])->load('indicatable');

            $submission = $indicatorTask->submissions()->create([
                'value' => $data['value'],
                'comment' => $data['comment'] ?? null,
                'submitter_id' => $submitter->id,
                'submitted_at' => now(),
                'is_achieved' => $this->determineIsAchieved($indicatorTask, $data['value']),
                'status' => $this->determineSubmissionStatus($indicatorTask),
            ]);

            // Handle attachments - both new files, existing attachment references, and string paths
            if (isset($data['attachments'])) {
                foreach ($data['attachments'] as $attachment) {
                    if ($attachment instanceof UploadedFile) {
                        // New file upload
                        $this->storeAttachment($submission, $attachment);
                    } elseif (is_array($attachment) && isset($attachment['id'])) {
                        // Existing attachment reference - copy it (cast to int to handle string IDs from frontend)
                        $this->copyExistingAttachment($submission, (int) $attachment['id']);
                    } elseif (is_string($attachment)) {
                        // Existing path from Filament
                        $this->storeFromPath($submission, $attachment);
                    }
                }
            }
            IndicatorSubmissionSubmitted::dispatch($submission);
            $this->flushDashboardCache($indicatorTask);

            return $submission;
        });

        return $submission;
    }

    /**
     * Flush the dashboard cache for the indicator task
     * Ensures that the entrepreneur doesn't see old data in the dashboard
     */
    private function flushDashboardCache(IndicatorTask $task): void
    {
        $seat = OrganisationProgrammeSeat::where('user_id', $task->entrepreneur->id)
            ->where('organisation_id', $task->organisation_id)
            ->where('programme_id', $task->programme_id)
            ->first();

        if (! $seat) {
            Log::warning('Indicator seat not found for indicator task', [
                'indicator_task_id' => $task->id,
                'user_id' => $task->entrepreneur->id,
                'organisation_id' => $task->organisation_id,
                'programme_id' => $task->programme_id,
            ]);

            return;
        }

        (new IndicatorDashboardGridService($seat))->flushCache();
    }

    private function storeAttachment(IndicatorSubmission $submission, UploadedFile $file): void
    {
        $disk = Storage::disk(IndicatorSubmissionAttachment::getDisk());
        $originalName = $file->getClientOriginalName();
        $folder = 'indicator_task_'.$submission->indicator_task_id;

        $uploader = new FileUploader($disk, $file, $folder);
        $path = $uploader->upload(fullPath: false);

        $submission->attachments()->create([
            'title' => $originalName,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);
    }

    private function copyExistingAttachment(IndicatorSubmission $submission, int $attachmentId): void
    {
        $existingAttachment = IndicatorSubmissionAttachment::find($attachmentId);

        if ($existingAttachment) {
            $submission->attachments()->create([
                'title' => $existingAttachment->title,
                'file_path' => $existingAttachment->file_path,
                'mime_type' => $existingAttachment->mime_type,
                'file_size' => $existingAttachment->file_size,
            ]);
        } else {
            throw new \InvalidArgumentException("Attachment with ID {$attachmentId} not found");
        }
    }

    private function determineSubmissionStatus(IndicatorTask $indicatorTask): IndicatorSubmissionStatusEnum
    {
        if ($indicatorTask->requiresVerification()) {
            return IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_1;
        }

        return IndicatorSubmissionStatusEnum::APPROVED;
    }

    private function determineIsAchieved(IndicatorTask $indicatorTask, $value): bool
    {
        $indicator = $indicatorTask->indicatable;
        $responseFormat = $indicator->response_format;
        $acceptanceValue = $indicator->acceptance_value;

        // If no acceptable value is set, the indicator is achieved
        if ($acceptanceValue === null) {
            return true;
        }

        if ($responseFormat === IndicatorResponseFormatEnum::BOOLEAN) {
            // Convert string value to integer for comparison with underlying database value
            $value = $value === 'true' ? '1' : '0';

            return $value === $acceptanceValue;
        }

        return $value >= $acceptanceValue;
    }

    /**
     * Create a submission from Filament form data
     */
    public function createSubmissionFromFilament(array $filamentData, User $submitter, int $indicatorTaskId): IndicatorSubmission
    {
        $transformedData = $this->transformFilamentData($filamentData, $indicatorTaskId);

        return $this->createSubmission($transformedData, $submitter);
    }

    /**
     * Transform Filament form data to the format expected by createSubmission
     */
    public function transformFilamentData(array $filamentData, int $indicatorTaskId): array
    {
        return [
            'indicator_task_id' => $indicatorTaskId,
            'value' => is_string($filamentData['value']) ? (float) $filamentData['value'] : $filamentData['value'],
            'comment' => $filamentData['comment'] ?? null,
            'attachments' => $this->transformFilamentAttachments($filamentData['attachments'] ?? []),
        ];
    }

    /**
     * Transform Filament file paths to clean paths
     */
    public function transformFilamentAttachments(array $filamentAttachments): array
    {
        if (empty($filamentAttachments)) {
            return [];
        }

        $attachments = [];
        foreach ($filamentAttachments as $filePath) {
            // Handle different possible path formats from Filament
            $cleanPath = $this->cleanFilamentPath($filePath);

            if (Storage::disk(IndicatorSubmissionAttachment::getDisk())->exists($cleanPath)) {
                $attachments[] = $cleanPath;
            }
        }

        return $attachments;
    }

    private function cleanFilamentPath(string $filePath): string
    {
        $cleanPath = $filePath;

        if (str_starts_with($filePath, 'storage/app/public/indicator_submissions/')) {
            $cleanPath = str_replace('storage/app/public/indicator_submissions/', '', $filePath);
        } elseif (str_starts_with($filePath, 'storage/app/indicator_submissions/')) {
            $cleanPath = str_replace('storage/app/indicator_submissions/', '', $filePath);
        } elseif (str_starts_with($filePath, 'indicator_submissions/')) {
            $cleanPath = str_replace('indicator_submissions/', '', $filePath);
        } elseif (str_starts_with($filePath, 'public/indicator_submissions/')) {
            $cleanPath = str_replace('public/indicator_submissions/', '', $filePath);
        }

        return $cleanPath;
    }

    private function storeFromPath(IndicatorSubmission $submission, string $path): void
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(IndicatorSubmissionAttachment::getDisk());

        if (! $disk->exists($path)) {
            return;
        }

        $submission->attachments()->create([
            'title' => basename($path),
            'file_path' => $path,
            'mime_type' => $disk->mimeType($path),
            'file_size' => $disk->size($path),
        ]);
    }
}
