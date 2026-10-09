<?php

namespace App\Services;

use App\Models\AchievementTier;

class ResearchAchievementTierService
{
    /** @var list<array{title: string, minimum: float, next: float|null}>|null */
    private ?array $cachedTiers = null;

    /**
     * @return list<array{title: string, minimum: float, next: float|null}>
     */
    public function tiers(): array
    {
        if ($this->cachedTiers !== null) {
            return $this->cachedTiers;
        }

        $records = AchievementTier::query()->orderBy('minimum_points')->get(['title', 'minimum_points'])->values();

        return $this->cachedTiers = $records
            ->map(fn (AchievementTier $tier, int $index): array => [
                'title' => $tier->title,
                'minimum' => $tier->minimum_points,
                'next' => $records->get($index + 1)?->minimum_points,
            ])
            ->all();
    }

    /**
     * @return array{title: string, minimum_points: float, next_title: string|null, next_threshold: float|null, points_to_next_tier: float, progress_percent: int}
     */
    public function forPoints(float $points): array
    {
        $points = max(0, $points);
        $tiers = $this->tiers();

        if ($tiers === []) {
            return [
                'title' => 'Unclassified',
                'minimum_points' => 0.0,
                'next_title' => null,
                'next_threshold' => null,
                'points_to_next_tier' => 0.0,
                'progress_percent' => 100,
            ];
        }

        $currentIndex = 0;

        foreach ($tiers as $index => $tier) {
            if ($points >= $tier['minimum']) {
                $currentIndex = $index;
            }
        }

        $current = $tiers[$currentIndex];
        $next = $tiers[$currentIndex + 1] ?? null;
        $pointsInTier = $points - $current['minimum'];
        $tierWidth = $next ? $next['minimum'] - $current['minimum'] : null;

        return [
            'title' => $current['title'],
            'minimum_points' => $current['minimum'],
            'next_title' => $next['title'] ?? null,
            'next_threshold' => $next['minimum'] ?? null,
            'points_to_next_tier' => $next ? round(max(0, $next['minimum'] - $points), 2) : 0,
            'progress_percent' => $tierWidth ? min(100, (int) floor(($pointsInTier / $tierWidth) * 100)) : 100,
        ];
    }
}
