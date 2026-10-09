<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SubmissionAttachment extends Model
{
    use HasFactory;

    protected $appends = ['kind', 'view_url', 'download_url'];

    protected $fillable = [
        'research_submission_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'category',
        'uploaded_by',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_submission_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    public function getKindAttribute(): string
    {
        return match ($this->extension()) {
            'png', 'jpg', 'jpeg', 'webp' => 'image',
            'pdf' => 'pdf',
            default => 'file',
        };
    }

    public function getViewUrlAttribute(): string
    {
        return route('submission-attachments.view', $this->id);
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('submission-attachments.download', $this->id);
    }
}
