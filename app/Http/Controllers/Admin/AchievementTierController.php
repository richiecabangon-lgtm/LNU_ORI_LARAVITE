<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AchievementTier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AchievementTierController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('Admin/AchievementTiers/Index', [
            'tiers' => AchievementTier::query()->orderBy('minimum_points')->get(['id', 'title', 'minimum_points'])
                ->map(fn (AchievementTier $tier): array => [
                    'id' => $tier->id,
                    'title' => $tier->title,
                    'minimum_points' => $tier->minimum_points,
                ])
                ->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'tiers' => ['required', 'array', 'min:1', 'max:12'],
            'tiers.*.id' => ['nullable', 'integer', 'exists:achievement_tiers,id'],
            'tiers.*.title' => ['required', 'string', 'max:80'],
            'tiers.*.minimum_points' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $tiers = collect($data['tiers'])->values();
        $titles = $tiers->map(fn (array $tier): string => mb_strtolower(trim($tier['title'])));
        $minimums = $tiers->map(fn (array $tier): string => number_format((float) $tier['minimum_points'], 2, '.', ''));

        if ($titles->unique()->count() !== $tiers->count()) {
            throw ValidationException::withMessages(['tiers' => 'Each legend title must be unique.']);
        }

        if ($minimums->unique()->count() !== $tiers->count()) {
            throw ValidationException::withMessages(['tiers' => 'Each legend must start at a different point value, so ranges do not overlap.']);
        }

        if ((float) $minimums->min() !== 0.0) {
            throw ValidationException::withMessages(['tiers' => 'The lowest legend must start at 0 points so every faculty member belongs to a tier.']);
        }

        DB::transaction(function () use ($tiers): void {
            $keptIds = $tiers->pluck('id')->filter()->all();
            AchievementTier::query()->whereNotIn('id', $keptIds)->delete();

            // Park existing minimums/titles to avoid transient unique collisions when values swap.
            foreach (AchievementTier::query()->get() as $existing) {
                $existing->update([
                    'title' => '__tmp_'.$existing->id,
                    'minimum_points' => -$existing->id,
                ]);
            }

            foreach ($tiers as $tier) {
                $attributes = ['title' => trim($tier['title']), 'minimum_points' => $tier['minimum_points']];
                if (! empty($tier['id'])) {
                    AchievementTier::query()->whereKey($tier['id'])->update($attributes);
                } else {
                    AchievementTier::query()->create($attributes);
                }
            }
        });

        return back()->with('success', 'Achievement legend saved.');
    }
}
