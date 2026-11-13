<?php

namespace App\Http\Controllers;

use App\Models\NewsAttachment;
use App\Models\NewsPost;
use App\Models\NewsPostReaction;
use App\Models\NewsPostView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('News/Index', [
            'posts' => NewsPost::query()->published()
                ->with(['attachments' => fn ($query) => $query->where('is_gallery', true)->orderBy('id')])
                ->withCount(['attachments as attachments_count' => fn ($query) => $query->where('is_gallery', false)])
                ->latest('published_at')->get()
                ->map(fn (NewsPost $post): array => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'category' => $post->category,
                    'summary' => $post->summary,
                    'cover_url' => $post->cover_url ?? ($post->attachments->first()
                        ? route('news.attachments.view', [$post->id, $post->attachments->first()->id])
                        : null),
                    'cover_image_alt' => $post->cover_image_alt ?? $post->attachments->first()?->alt_text,
                    'published_at' => $post->published_at?->toIso8601String(),
                    'attachments_count' => $post->attachments_count,
                ])
                ->all(),
        ]);
    }

    public function show(Request $request, NewsPost $news): Response
    {
        abort_unless(NewsPost::query()->published()->whereKey($news->id)->exists(), 404);

        $viewer = $request->user();
        $timestamp = now();
        NewsPostView::query()->insertOrIgnore([[
            'news_post_id' => $news->id,
            'user_id' => $viewer->id,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]]);

        $reactionCounts = array_fill_keys(NewsPostReaction::REACTIONS, 0);
        foreach ($news->reactions()->selectRaw('reaction, COUNT(*) as count')->groupBy('reaction')->get() as $reaction) {
            $reactionCounts[$reaction->reaction] = (int) $reaction->count;
        }
        $comments = $news->comments()->with('user:id,name')->latest()->paginate(15)->through(
            fn ($comment): array => [
                'id' => $comment->id,
                'user_name' => $comment->user->name,
                'body' => $comment->body,
                'created_at' => $comment->created_at?->toIso8601String(),
                'is_edited' => $comment->updated_at?->gt($comment->created_at) ?? false,
                'can_manage' => $viewer->is_active && ($viewer->id === $comment->user_id || $viewer->isAdmin()),
            ],
        );

        return Inertia::render('News/Show', [
            'post' => [
                'id' => $news->id,
                'title' => $news->title,
                'category' => $news->category,
                'cover_url' => $news->cover_url,
                'cover_image_alt' => $news->cover_image_alt,
                'summary' => $news->summary,
                'body' => $news->body,
                'published_at' => $news->published_at?->toIso8601String(),
                'view_count' => $news->views()->count(),
                'reaction_counts' => $reactionCounts,
                'user_reaction' => $news->reactions()->where('user_id', $viewer->id)->value('reaction'),
                'comment_count' => $news->comments()->count(),
                'comments' => $comments,
                'gallery_images' => $news->attachments()->where('is_gallery', true)->orderBy('id')->get()
                    ->map(fn (NewsAttachment $image): array => [
                        'id' => $image->id,
                        'alt_text' => $image->alt_text,
                        'url' => route('news.attachments.view', [$news->id, $image->id]),
                    ])->all(),
                'attachments' => $news->attachments()->where('is_gallery', false)->get()->map(fn (NewsAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'size' => $attachment->size,
                    'kind' => $this->previewKind($attachment),
                ])->all(),
            ],
        ]);
    }

    public function download(Request $request, NewsPost $news, NewsAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->news_post_id === $news->id, 404);
        abort_unless($request->user()->isAdmin() || NewsPost::query()->published()->whereKey($news->id)->exists(), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function view(Request $request, NewsPost $news, NewsAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->news_post_id === $news->id, 404);
        abort_unless($request->user()->isAdmin() || NewsPost::query()->published()->whereKey($news->id)->exists(), 404);
        $kind = $this->previewKind($attachment);
        abort_if($kind === 'file', 404);

        $extension = strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        // Content type is derived from the extension, never from the uploader's claimed MIME type.
        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function cover(Request $request, NewsPost $news): StreamedResponse
    {
        abort_unless($news->cover_image_path, 404);
        abort_unless($request->user()->isAdmin() || NewsPost::query()->published()->whereKey($news->id)->exists(), 404);

        $contentType = match (strtolower(pathinfo($news->cover_image_path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return Storage::disk('local')->response($news->cover_image_path, null, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function previewKind(NewsAttachment $attachment): string
    {
        return match (strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION))) {
            'png', 'jpg', 'jpeg', 'webp' => 'image',
            'pdf' => 'pdf',
            default => 'file',
        };
    }
}
