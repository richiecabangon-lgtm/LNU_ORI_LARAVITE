<?php

use App\Models\NewsPost;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('lets admins publish a post with a document that faculty can read and download', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.news.store'), [
            'title' => 'Innovation incentives',
            'category' => 'Incentives & Guidelines',
            'body' => 'Patent incentive is Php 100,000.',
            'is_published' => true,
            'attachments' => [UploadedFile::fake()->create('brochure.pdf', 200, 'application/pdf')],
        ])
        ->assertRedirect(route('admin.news.index'));

    $post = NewsPost::query()->with('attachments')->firstOrFail();
    Storage::disk('local')->assertExists($post->attachments->first()->path);

    $faculty = User::factory()->faculty()->create();
    $this->actingAs($faculty)->get(route('news.index'))
        ->assertInertia(fn ($page) => $page->has('posts', 1)->where('posts.0.title', 'Innovation incentives'));
    $this->actingAs($faculty)->get(route('news.show', $post))->assertOk();
    $this->actingAs($faculty)->get(route('news.attachments.download', [$post, $post->attachments->first()]))->assertOk();
});

it('hides drafts from faculty', function () {
    $draft = NewsPost::factory()->create(['is_published' => false, 'published_at' => null]);
    $faculty = User::factory()->faculty()->create();

    $this->actingAs($faculty)->get(route('news.index'))->assertInertia(fn ($page) => $page->has('posts', 0));
    $this->actingAs($faculty)->get(route('news.show', $draft))->assertNotFound();
});

it('forbids non-admins from managing news', function () {
    $faculty = User::factory()->faculty()->create();

    $this->actingAs($faculty)->get(route('admin.news.index'))->assertForbidden();
    $this->actingAs($faculty)->post(route('admin.news.store'), ['title' => 'x', 'body' => 'y', 'is_published' => true])->assertForbidden();
});

it('rejects unsupported document types', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.news.store'), [
            'title' => 'Bad', 'body' => 'file', 'is_published' => true,
            'attachments' => [UploadedFile::fake()->create('run.exe', 10)],
        ])
        ->assertSessionHasErrors('attachments.0');
});

it('removes documents with a deleted post', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.news.store'), [
        'title' => 'T', 'category' => 'Event', 'body' => 'B', 'is_published' => true,
        'attachments' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
    ]);
    $post = NewsPost::query()->with('attachments')->firstOrFail();
    $path = $post->attachments->first()->path;

    $this->actingAs($admin)->delete(route('admin.news.destroy', $post))->assertRedirect();

    Storage::disk('local')->assertMissing($path);
    expect(NewsPost::query()->count())->toBe(0);
});

it('serves pdf and image documents inline and refuses other file types', function () {
    Storage::fake('local');
    $post = NewsPost::factory()->create();
    $pdf = $post->attachments()->create(['disk' => 'local', 'path' => 'news/1/a.pdf', 'original_name' => 'a.pdf', 'size' => 1]);
    $doc = $post->attachments()->create(['disk' => 'local', 'path' => 'news/1/b.docx', 'original_name' => 'b.docx', 'size' => 1]);
    Storage::disk('local')->put('news/1/a.pdf', '%PDF-1.4');
    Storage::disk('local')->put('news/1/b.docx', 'x');
    $faculty = User::factory()->faculty()->create();

    $this->actingAs($faculty)->get(route('news.attachments.view', [$post, $pdf]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($faculty)->get(route('news.attachments.view', [$post, $doc]))->assertNotFound();
    $this->actingAs($faculty)->get(route('news.show', $post))
        ->assertInertia(fn ($page) => $page->where('post.attachments.0.kind', 'pdf')->where('post.attachments.1.kind', 'file'));
});

it('publishes a news post with a cover image and requires a description for it', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $payload = ['title' => 'Research award', 'category' => 'Achievement', 'body' => 'Congratulations.', 'is_published' => true];

    $this->actingAs($admin)->post(route('admin.news.store'), [...$payload, 'cover_image' => UploadedFile::fake()->image('cover.jpg', 1200, 600)])
        ->assertSessionHasErrors('cover_image_alt');

    $this->actingAs($admin)->post(route('admin.news.store'), [...$payload, 'cover_image' => UploadedFile::fake()->image('cover.jpg', 1200, 600), 'cover_image_alt' => 'Team holding the award'])
        ->assertSessionHasNoErrors();

    $post = NewsPost::query()->firstOrFail();
    Storage::disk('local')->assertExists($post->cover_image_path);

    $faculty = User::factory()->faculty()->create();
    $this->actingAs($faculty)->get(route('news.index'))
        ->assertInertia(fn ($page) => $page->where('posts.0.category', 'Achievement')->where('posts.0.cover_image_alt', 'Team holding the award'));
    $this->actingAs($faculty)->get(route('news.cover', $post))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    $this->actingAs($admin)->put(route('admin.news.update', $post), [...$payload, 'remove_cover_image' => true])->assertSessionHasNoErrors();
    Storage::disk('local')->assertMissing($post->cover_image_path);
    expect($post->fresh()->cover_image_path)->toBeNull();
});

it('rejects tiny or invalid cover images and unknown categories, and hides covers of drafts', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $payload = ['title' => 'T', 'body' => 'B', 'is_published' => false, 'category' => 'Achievement'];

    $this->actingAs($admin)->post(route('admin.news.store'), [...$payload, 'cover_image' => UploadedFile::fake()->image('tiny.jpg', 100, 50), 'cover_image_alt' => 'x'])->assertSessionHasErrors('cover_image');
    $this->actingAs($admin)->post(route('admin.news.store'), [...$payload, 'category' => 'Gossip'])->assertSessionHasErrors('category');

    $this->actingAs($admin)->post(route('admin.news.store'), [...$payload, 'cover_image' => UploadedFile::fake()->image('c.png', 800, 400), 'cover_image_alt' => 'Draft cover']);
    $draft = NewsPost::query()->firstOrFail();
    $this->actingAs(User::factory()->faculty()->create())->get(route('news.cover', $draft))->assertNotFound();
});
