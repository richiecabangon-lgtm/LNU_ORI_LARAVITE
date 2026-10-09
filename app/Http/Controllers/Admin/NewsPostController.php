<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsAttachment;
use App\Models\NewsPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NewsPostController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('Admin/News/Index', [
            'posts' => NewsPost::query()->withCount('attachments')->latest('id')->get()
                ->map(fn (NewsPost $post): array => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'category' => $post->category,
                    'cover_url' => $post->cover_url,
                    'summary' => $post->summary,
                    'is_published' => $post->is_published,
                    'published_at' => $post->published_at?->toIso8601String(),
                    'attachments_count' => $post->attachments_count,
                ])
                ->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('Admin/News/Form', ['post' => null, 'categories' => NewsPost::CATEGORIES]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $this->validated($request);
        $post = NewsPost::query()->create([
            ...$this->postAttributes($data),
            'created_by' => $request->user()->id,
        ]);
        $this->storeCover($request, $post);
        $this->storeAttachments($request, $post);

        return to_route('admin.news.index')->with('success', 'News post saved.');
    }

    public function edit(Request $request, NewsPost $news): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('Admin/News/Form', [
            'categories' => NewsPost::CATEGORIES,
            'post' => [
                'id' => $news->id,
                'title' => $news->title,
                'category' => $news->category,
                'cover_url' => $news->cover_url,
                'cover_image_alt' => $news->cover_image_alt,
                'summary' => $news->summary,
                'body' => $news->body,
                'is_published' => $news->is_published,
                'attachments' => $news->attachments->map(fn (NewsAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'size' => $attachment->size,
                ])->all(),
            ],
        ]);
    }

    public function update(Request $request, NewsPost $news): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $this->validated($request);
        $attributes = $this->postAttributes($data);
        if ($news->published_at !== null && $attributes['is_published']) {
            $attributes['published_at'] = $news->published_at;
        }
        $news->update($attributes);
        $this->storeCover($request, $news);
        $this->storeAttachments($request, $news);

        return to_route('admin.news.index')->with('success', 'News post updated.');
    }

    public function destroy(Request $request, NewsPost $news): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $news->attachments->each(fn (NewsAttachment $attachment) => Storage::disk($attachment->disk)->delete($attachment->path));
        if ($news->cover_image_path) {
            Storage::disk('local')->delete($news->cover_image_path);
        }
        $news->delete();

        return to_route('admin.news.index')->with('success', 'News post deleted.');
    }

    public function destroyAttachment(Request $request, NewsPost $news, NewsAttachment $attachment): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($attachment->news_post_id === $news->id, 404);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'Document removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(NewsPost::CATEGORIES)],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:20000'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=600,min_height=300'],
            'cover_image_alt' => ['nullable', 'string', 'max:255', 'required_with:cover_image'],
            'remove_cover_image' => ['sometimes', 'boolean'],
            'is_published' => ['required', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg', 'max:20480'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{title: string, summary: string|null, body: string, is_published: bool, published_at: Carbon|null}
     */
    private function postAttributes(array $data): array
    {
        return [
            'title' => $data['title'],
            'category' => $data['category'],
            'summary' => $data['summary'] ?? null,
            'body' => $data['body'],
            'is_published' => (bool) $data['is_published'],
            'published_at' => $data['is_published'] ? now() : null,
        ];
    }

    private function storeCover(Request $request, NewsPost $post): void
    {
        $replace = $request->hasFile('cover_image');
        if (($replace || $request->boolean('remove_cover_image')) && $post->cover_image_path) {
            Storage::disk('local')->delete($post->cover_image_path);
            $post->update(['cover_image_path' => null, 'cover_image_alt' => null]);
        }

        if ($replace) {
            $post->update([
                'cover_image_path' => $request->file('cover_image')->store('news/'.$post->id, 'local'),
                'cover_image_alt' => $request->string('cover_image_alt')->toString(),
            ]);
        } elseif ($post->cover_image_path && $request->filled('cover_image_alt')) {
            $post->update(['cover_image_alt' => $request->string('cover_image_alt')->toString()]);
        }
    }

    private function storeAttachments(Request $request, NewsPost $post): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $post->attachments()->create([
                'disk' => 'local',
                'path' => $file->store('news/'.$post->id, 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }
}
