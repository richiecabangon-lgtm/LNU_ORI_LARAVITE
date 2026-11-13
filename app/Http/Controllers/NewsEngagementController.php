<?php

namespace App\Http\Controllers;

use App\Models\NewsComment;
use App\Models\NewsPost;
use App\Models\NewsPostReaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class NewsEngagementController extends Controller
{
    public function storeReaction(Request $request, NewsPost $news): RedirectResponse
    {
        $this->ensurePublished($news);
        abort_unless($request->user()->is_active, 403);

        $data = $request->validate([
            'reaction' => ['required', Rule::in(NewsPostReaction::REACTIONS)],
        ]);
        $timestamp = now();

        NewsPostReaction::query()->upsert([[
            'news_post_id' => $news->id,
            'user_id' => $request->user()->id,
            'reaction' => $data['reaction'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]], ['news_post_id', 'user_id'], ['reaction', 'updated_at']);

        return back()->with('success', 'Reaction saved.');
    }

    public function destroyReaction(Request $request, NewsPost $news): RedirectResponse
    {
        $this->ensurePublished($news);
        abort_unless($request->user()->is_active, 403);

        $news->reactions()->where('user_id', $request->user()->id)->delete();

        return back()->with('success', 'Reaction removed.');
    }

    public function storeComment(Request $request, NewsPost $news): RedirectResponse
    {
        $this->ensurePublished($news);
        abort_unless($request->user()->is_active, 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $news->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return to_route('news.show', $news)->with('success', 'Comment added.');
    }

    public function updateComment(Request $request, NewsPost $news, NewsComment $comment): RedirectResponse
    {
        $this->ensurePublished($news);
        abort_unless($comment->news_post_id === $news->id, 404);
        Gate::authorize('update', $comment);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);
        $comment->update(['body' => $data['body']]);

        return back()->with('success', 'Comment updated.');
    }

    public function destroyComment(Request $request, NewsPost $news, NewsComment $comment): RedirectResponse
    {
        $this->ensurePublished($news);
        abort_unless($comment->news_post_id === $news->id, 404);
        Gate::authorize('delete', $comment);
        $comment->delete();

        return back()->with('success', 'Comment removed.');
    }

    private function ensurePublished(NewsPost $news): void
    {
        abort_unless(NewsPost::query()->published()->whereKey($news->id)->exists(), 404);
    }
}
