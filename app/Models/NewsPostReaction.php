<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsPostReaction extends Model
{
    public const REACTIONS = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];

    protected $fillable = ['news_post_id', 'user_id', 'reaction'];

    public function newsPost(): BelongsTo
    {
        return $this->belongsTo(NewsPost::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
