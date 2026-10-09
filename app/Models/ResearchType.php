<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResearchType extends Model
{
    use SoftDeletes;

    protected $fillable = ['slug', 'name', 'description', 'category', 'has_authors', 'is_active', 'sort_order', 'updated_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'has_authors' => 'boolean'];
    }

    public function pointSetting(): HasOne
    {
        return $this->hasOne(ResearchTypePointSetting::class, 'research_type', 'slug');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return static::query()->active()->orderBy('sort_order')->orderBy('name')->get(['slug', 'name'])
            ->map(fn (self $type): array => ['value' => $type->slug, 'label' => $type->name])
            ->all();
    }
}
