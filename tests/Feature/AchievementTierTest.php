<?php

use App\Models\AchievementTier;
use App\Models\User;
use App\Services\ResearchAchievementTierService;

it('lets admins view the achievement legend', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.achievement-tiers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/AchievementTiers/Index')->has('tiers', 5));
});

it('forbids non-admins from the achievement legend', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route('admin.achievement-tiers.index'))->assertForbidden();
    $this->actingAs($user)->put(route('admin.achievement-tiers.update'), ['tiers' => []])->assertForbidden();
})->with(['faculty', 'coordinator']);

it('replaces the legend and drives tier lookup', function () {
    $existing = AchievementTier::query()->orderBy('minimum_points')->first();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.achievement-tiers.update'), ['tiers' => [
            ['id' => $existing->id, 'title' => 'Starter', 'minimum_points' => 0],
            ['id' => null, 'title' => 'Master', 'minimum_points' => 50],
        ]])
        ->assertSessionHasNoErrors();

    expect(AchievementTier::query()->count())->toBe(2);

    $service = new ResearchAchievementTierService;
    expect($service->forPoints(0)['title'])->toBe('Starter')
        ->and($service->forPoints(49.99)['title'])->toBe('Starter')
        ->and($service->forPoints(50)['title'])->toBe('Master')
        ->and($service->forPoints(50)['next_title'])->toBeNull();
});

it('rejects invalid legends', function (array $rows) {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.achievement-tiers.update'), ['tiers' => $rows])
        ->assertSessionHasErrors();

    expect(AchievementTier::query()->count())->toBe(5);
})->with([
    'first tier not zero' => [[['title' => 'A', 'minimum_points' => 10], ['title' => 'B', 'minimum_points' => 20]]],
    'duplicate minimums' => [[['title' => 'A', 'minimum_points' => 0], ['title' => 'B', 'minimum_points' => 0]]],
    'duplicate titles' => [[['title' => 'A', 'minimum_points' => 0], ['title' => 'a', 'minimum_points' => 20]]],
    'negative points' => [[['title' => 'A', 'minimum_points' => -1]]],
    'empty' => [[]],
]);
