<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    use HasFactory;

    protected $fillable = [
        'research_submission_id',
        'admin_id',
        'decision',
        'final_points',
        'remarks',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'final_points' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_submission_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function isApproved(): bool
    {
        return $this->decision === 'approved';
    }
}
