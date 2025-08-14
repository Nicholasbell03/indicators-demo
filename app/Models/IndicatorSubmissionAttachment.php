<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class IndicatorSubmissionAttachment extends Model
{
    use HasFactory;

    protected $table = 'indicator_submission_attachments';

    public const DISK = 'indicator_submissions';

    public static function getDisk(): string
    {
        return app()->environment('testing') ? 'indicator_submissions_local' : self::DISK;
    }

    protected $guarded = [];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(IndicatorSubmission::class, 'indicator_submission_id');
    }

    public function getFileUrlAttribute(): string
    {
        return Storage::disk(self::getDisk())->url($this->file_path);
    }
}
