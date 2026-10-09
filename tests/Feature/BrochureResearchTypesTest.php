<?php

use App\Models\ResearchType;
use App\Models\ResearchTypePointSetting;

it('seeds the brochure point schedule and archives legacy types', function () {
    $points = fn (string $name): float => (float) ResearchTypePointSetting::query()
        ->where('research_type', ResearchType::query()->where('name', $name)->value('slug'))
        ->value('points_per_output');

    expect($points('Published individual research - Scopus-indexed journal'))->toBe(360.0)
        ->and($points('Patent (if granted)'))->toBe(400.0)
        ->and($points('Adviser - completed dissertation thesis'))->toBe(60.0)
        ->and($points('Attendance - International (per day)'))->toBe(20.0)
        ->and(ResearchType::query()->active()->where('slug', 'journal_article')->exists())->toBeFalse()
        ->and(ResearchType::query()->active()->whereNull('category')->count())->toBe(0);
});

it('gives every active type a unique key and a point value', function () {
    $active = ResearchType::query()->active()->pluck('slug');

    expect($active->unique())->toHaveCount($active->count())
        ->and(ResearchTypePointSetting::query()->whereIn('research_type', $active)->count())->toBe($active->count());
});
