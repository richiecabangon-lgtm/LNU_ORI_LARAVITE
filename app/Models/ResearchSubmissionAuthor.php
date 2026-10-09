<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchSubmissionAuthor extends Model
{
    use HasFactory;

    protected $hidden = ['authorization_disk', 'authorization_path'];

    protected $appends = ['has_authorization', 'authorization_kind'];

    protected $fillable = [
        'research_submission_id',
        'faculty_id',
        'name',
        'email',
        'institution',
        'author_order',
        'allocated_points',
        'authorization_disk',
        'authorization_path',
        'authorization_original_name',
    ];

    protected function casts(): array
    {
        return ['allocated_points' => 'decimal:2'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_submission_id');
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'faculty_id');
    }

    protected function getHasAuthorizationAttribute(): bool
    {
        return $this->authorization_path !== null;
    }

    protected function getAuthorizationKindAttribute(): string
    {
        return match (strtolower(pathinfo((string) $this->authorization_original_name, PATHINFO_EXTENSION))) {
            'png', 'jpg', 'jpeg' => 'image',
            'pdf' => 'pdf',
            default => 'file',
        };
    }
}
