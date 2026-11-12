<?php

namespace App\Http\Requests\Concerns;

use App\Models\ResearchType;
use App\Services\ResearchClaimCalculator;
use Illuminate\Validation\Rule;

trait CreditsSoleParticipant
{
    /**
     * Role-based involvements (refereeing, thesis panels, etc.) credit the submitting faculty alone.
     */
    protected function prepareForValidation(): void
    {
        $type = ResearchType::query()->active()->where('slug', $this->input('research_type'))->first();
        $user = $this->user();

        if (! $type || $type->has_authors || ! $user) {
            return;
        }

        $quantity = $this->input('quantity');
        $calculator = app(ResearchClaimCalculator::class);
        $unit = $calculator->unit($this, $type);
        $submission = $this->route('submission');
        $rate = $submission && $submission->research_type === $type->slug && $submission->unit_snapshot !== null
            ? $submission->rate_snapshot : $type->pointSetting?->points_per_output;
        $points = round((float) $rate * ($unit !== 'output' && is_numeric($quantity) ? (float) $quantity : 1), 2);
        if ($calculator->isLegacyEdit($this, $type) && $submission->claimed_points !== null) {
            $points = (float) $submission->claimed_points;
        }

        $this->merge([
            'authors' => [$user->name],
            'author_details' => [[
                'name' => $user->name,
                'email' => $user->email,
                'institution' => 'Leyte Normal University',
                'allocated_points' => $points,
            ]],
        ]);
    }

    protected function quantityRules(): array
    {
        $type = ResearchType::query()->active()->where('slug', $this->input('research_type'))->first();
        if (! $type) {
            return ['nullable'];
        }

        $calculator = app(ResearchClaimCalculator::class);
        if ($calculator->isLegacyEdit($this, $type)) {
            return ['nullable'];
        }

        return match ($calculator->unit($this, $type)) {
            'hour' => ['required', 'numeric', 'gt:0', 'max:999999.99', 'decimal:0,2'],
            'day' => ['required', 'integer', 'min:1', 'max:999999'],
            default => ['nullable', Rule::prohibitedIf(true)],
        };
    }
}
