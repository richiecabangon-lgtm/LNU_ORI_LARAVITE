<?php

namespace App\Models;

use Database\Factories\AchievementTierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AchievementTier extends Model
{
    /** @use HasFactory<AchievementTierFactory> */
    use HasFactory;

    protected $fillable = ['title', 'minimum_points'];

    protected function casts(): array
    {
        return ['minimum_points' => 'float'];
    }
}
