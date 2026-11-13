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
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewsPostController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('Admin/News/Index', [
            'posts' => NewsPost::query()
                ->with(['attachments' => fn ($query) => $query->where('is_gallery', true)->orderBy('id')])
                ->withCount(['attachments as attachments_count' => fn ($query) => $query->where('is_gallery', false)])
                ->latest('id')->get()
                ->map(fn (NewsPost $post): array => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'category' => $post->category,
                    'cover_url' => $post->cover_url ?? ($post->attachments->first()
                        ? route('news.attachments.view', [$post->id, $post->attachments->first()->id])
                        : null),
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
        $this->ensureGalleryCapacity(null, $data);
        $post = NewsPost::query()->create([
            ...$this->postAttributes($data),
            'created_by' => $request->user()->id,
        ]);
        $this->storeCover($request, $post);
        $this->storeAttachments($request, $post);
        $this->storeGalleryImages($request, $post, $data);

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
                'gallery_images' => $news->attachments()->where('is_gallery', true)->orderBy('id')->get()
                    ->map(fn (NewsAttachment $image): array => [
                        'id' => $image->id,
                        'original_name' => $image->original_name,
                        'alt_text' => $image->alt_text,
                        'url' => route('news.attachments.view', [$news->id, $image->id]),
                    ])->all(),
                'summary' => $news->summary,
                'body' => $news->body,
                'is_published' => $news->is_published,
                'attachments' => $news->attachments()->where('is_gallery', false)->get()->map(fn (NewsAttachment $attachment): array => [
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
        $this->ensureGalleryCapacity($news, $data);
        $attributes = $this->postAttributes($data);
        if ($news->published_at !== null && $attributes['is_published']) {
            $attributes['published_at'] = $news->published_at;
        }
        $news->update($attributes);
        $this->storeCover($request, $news);
        $this->storeAttachments($request, $news);
        $this->storeGalleryImages($request, $news, $data);

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

        return back()->with('success', $attachment->is_gallery ? 'Gallery image removed.' : 'Document removed.');
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
            'gallery_images' => ['nullable', 'array', 'max:10'],
            'gallery_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=600,min_height=300'],
            'gallery_image_alts' => ['required_with:gallery_images', 'array', 'max:10'],
            'gallery_image_alts.*' => ['required', 'string', 'max:255'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureGalleryCapacity(?NewsPost $post, array $data): void
    {
        $images = $data['gallery_images'] ?? [];
        $alts = $data['gallery_image_alts'] ?? [];

        if (count($images) !== count($alts)) {
            throw ValidationException::withMessages(['gallery_image_alts' => 'Add a description for every gallery image.']);
        }

        $existingCount = $post?->attachments()->where('is_gallery', true)->count() ?? 0;
        if ($existingCount + count($images) > 10) {
            throw ValidationException::withMessages(['gallery_images' => 'A news post can have up to 10 gallery images.']);
        }
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeGalleryImages(Request $request, NewsPost $post, array $data): void
    {
        foreach ($request->file('gallery_images', []) as $index => $file) {
            $post->attachments()->create([
                'disk' => 'local',
                'path' => $file->store('news/'.$post->id.'/gallery', 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'is_gallery' => true,
                'alt_text' => $data['gallery_image_alts'][$index],
            ]);
        }
    }
}
