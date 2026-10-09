<?php

namespace App\Models;

use App\Enums\EvaluationRecommendation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'research_submission_id',
        'coordinator_id',
        'score',
        'quality_rating',
        'relevance_rating',
        'documentation_rating',
        'recommendation',
        'awarded_points',
        'remarks',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'recommendation' => EvaluationRecommendation::class,
            'score' => 'decimal:2',
            'awarded_points' => 'decimal:2',
            'evaluated_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_submission_id');
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }
}
