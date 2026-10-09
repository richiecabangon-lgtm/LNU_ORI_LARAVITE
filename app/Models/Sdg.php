<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sdg extends Model
{
    use HasFactory;

    protected $appends = ['image_url'];

    protected $fillable = ['number', 'name', 'description', 'color', 'is_active', 'image_path'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? '/storage/'.ltrim($this->image_path, '/') : null;
    }

    protected function casts(): array
    {
        return ['number' => 'integer', 'is_active' => 'boolean'];
    }

    public function submissions(): BelongsToMany
    {
        return $this->belongsToMany(ResearchSubmission::class, 'research_submission_sdg');
    }
}
