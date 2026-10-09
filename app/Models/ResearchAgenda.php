<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ResearchAgenda extends Model
{
    use HasFactory;

    protected $appends = ['image_url'];

    protected $fillable = ['name', 'description', 'is_active', 'image_path'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? '/storage/'.ltrim($this->image_path, '/') : null;
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function submissions(): BelongsToMany
    {
        return $this->belongsToMany(ResearchSubmission::class, 'research_agenda_submission', 'research_agenda_id', 'research_submission_id');
    }
}
