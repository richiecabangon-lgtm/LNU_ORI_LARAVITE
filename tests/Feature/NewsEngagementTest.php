<?php

use App\Models\NewsComment;
use App\Models\NewsPost;
use App\Models\NewsPostReaction;
use App\Models\NewsPostView;
use App\Models\User;

it('counts one view per signed-in user and lets them change or remove their reaction', function () {
    $post = NewsPost::factory()->create();
    $firstReader = User::factory()->faculty()->create();
    $secondReader = User::factory()->faculty()->create();
    $showUrl = route('news.show', $post);

    $this->actingAs($firstReader)->get($showUrl)
        ->assertInertia(fn ($page) => $page->where('post.view_count', 1));
    $this->actingAs($firstReader)->get($showUrl)
        ->assertInertia(fn ($page) => $page->where('post.view_count', 1));
    $this->actingAs($secondReader)->get($showUrl)
        ->assertInertia(fn ($page) => $page->where('post.view_count', 2));

    expect(NewsPostView::query()->where('news_post_id', $post->id)->count())->toBe(2);

    $reactionUrl = route('news.reactions.store', $post);
    $this->actingAs($firstReader)->from($showUrl)->post($reactionUrl, ['reaction' => 'love'])->assertRedirect($showUrl);
    $this->actingAs($firstReader)->from($showUrl)->post($reactionUrl, ['reaction' => 'wow'])->assertRedirect($showUrl);
    $this->actingAs($secondReader)->from($showUrl)->post($reactionUrl, ['reaction' => 'like'])->assertRedirect($showUrl);

    expect(NewsPostReaction::query()->where('news_post_id', $post->id)->count())->toBe(2);
    expect(NewsPostReaction::query()->where('news_post_id', $post->id)->where('user_id', $firstReader->id)->value('reaction'))->toBe('wow');

    $this->actingAs($firstReader)->from($showUrl)->delete(route('news.reactions.destroy', $post))->assertRedirect($showUrl);
    expect(NewsPostReaction::query()->where('news_post_id', $post->id)->count())->toBe(1);
});

it('lets comment authors edit comments and admins moderate them', function () {
    $post = NewsPost::factory()->create();
    $author = User::factory()->faculty()->create();
    $otherUser = User::factory()->faculty()->create();
    $admin = User::factory()->admin()->create();
    $showUrl = route('news.show', $post);

    $this->actingAs($author)->from($showUrl)->post(route('news.comments.store', $post), ['body' => 'Initial comment'])
        ->assertRedirect($showUrl);
    $comment = NewsComment::query()->firstOrFail();

    $this->actingAs($author)->from($showUrl)->put(route('news.comments.update', [$post, $comment]), ['body' => 'Updated comment'])
        ->assertRedirect($showUrl);
    expect($comment->fresh()->body)->toBe('Updated comment');

    $this->actingAs($otherUser)->put(route('news.comments.update', [$post, $comment]), ['body' => 'Unauthorized edit'])
        ->assertForbidden();
    $this->actingAs($otherUser)->delete(route('news.comments.destroy', [$post, $comment]))
        ->assertForbidden();

    $this->actingAs($author)->from($showUrl)->delete(route('news.comments.destroy', [$post, $comment]))
        ->assertRedirect($showUrl);
    expect(NewsComment::query()->whereKey($comment->id)->exists())->toBeFalse();

    $adminComment = $post->comments()->create(['user_id' => $author->id, 'body' => 'Admin can moderate this comment']);
    $this->actingAs($otherUser)->get(route('news.show', $post))
        ->assertInertia(fn ($page) => $page->where('post.comments.data.0.can_manage', false));

    $this->actingAs($admin)->from($showUrl)->delete(route('news.comments.destroy', [$post, $adminComment]))
        ->assertRedirect($showUrl);
    expect(NewsComment::query()->whereKey($adminComment->id)->exists())->toBeFalse();
});

it('rejects invalid reactions and prevents comments from crossing posts', function () {
    $post = NewsPost::factory()->create();
    $otherPost = NewsPost::factory()->create();
    $author = User::factory()->faculty()->create();
    $otherPostComment = NewsComment::query()->create([
        'news_post_id' => $otherPost->id,
        'user_id' => $author->id,
        'body' => 'Belongs to another post',
    ]);

    $this->actingAs($author)->from(route('news.show', $post))
        ->post(route('news.reactions.store', $post), ['reaction' => 'not-an-emoji'])
        ->assertSessionHasErrors('reaction');
    $this->actingAs($author)->put(route('news.comments.update', [$post, $otherPostComment]), ['body' => 'Cross-post edit'])
        ->assertNotFound();
    $this->actingAs($author)->post(route('news.comments.store', $post), ['body' => str_repeat('x', 2001)])
        ->assertSessionHasErrors('body');
});

it('requires authentication before users can react or comment', function () {
    $post = NewsPost::factory()->create();

    $this->post(route('news.reactions.store', $post), ['reaction' => 'like'])->assertRedirect(route('login'));
    $this->post(route('news.comments.store', $post), ['body' => 'Guest comment'])->assertRedirect(route('login'));
});
