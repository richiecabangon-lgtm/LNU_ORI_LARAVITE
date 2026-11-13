<?php

namespace App\Policies;

use App\Models\NewsComment;
use App\Models\User;

class NewsCommentPolicy
{
    public function update(User $user, NewsComment $comment): bool
    {
        return $user->is_active && ($user->id === $comment->user_id || $user->isAdmin());
    }

    public function delete(User $user, NewsComment $comment): bool
    {
        return $user->is_active && ($user->id === $comment->user_id || $user->isAdmin());
    }
}
