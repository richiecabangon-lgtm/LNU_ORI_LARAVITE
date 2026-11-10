<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchEvidenceRequirement extends Model
{
    protected $fillable = ['research_type_id', 'name', 'is_required', 'is_active'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'is_active' => 'boolean'];
    }

    public function researchType(): BelongsTo
    {
        return $this->belongsTo(ResearchType::class);
    }
}
