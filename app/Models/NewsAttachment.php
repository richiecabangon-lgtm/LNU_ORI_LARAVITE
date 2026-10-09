<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsAttachment extends Model
{
    protected $fillable = ['news_post_id', 'disk', 'path', 'original_name', 'mime_type', 'size'];

    public function newsPost(): BelongsTo
    {
        return $this->belongsTo(NewsPost::class);
    }
}
