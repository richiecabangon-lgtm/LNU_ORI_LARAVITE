<?php

namespace App\Http\Requests\Concerns;

use App\Models\ResearchType;
use App\Models\ResearchTypePointSetting;

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

        $points = (float) ResearchTypePointSetting::query()->where('research_type', $type->slug)->value('points_per_output');

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
}
