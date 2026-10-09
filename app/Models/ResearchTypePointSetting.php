<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchTypePointSetting extends Model
{
    protected $fillable = ['research_type', 'points_per_output', 'updated_by'];

    protected function casts(): array
    {
        return ['points_per_output' => 'decimal:2'];
    }

    public function researchType(): BelongsTo
    {
        return $this->belongsTo(ResearchType::class, 'research_type', 'slug');
    }
}
