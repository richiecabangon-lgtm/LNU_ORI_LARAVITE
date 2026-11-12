<?php

namespace App\Services;

use App\Models\ResearchSubmission;
use App\Models\ResearchType;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ResearchClaimCalculator
{
    public function unit(Request $request, ResearchType $type): string
    {
        $submission = $request->route('submission');

        return $submission instanceof ResearchSubmission && $submission->research_type === $type->slug && $submission->unit_snapshot !== null
            ? $submission->unit_snapshot
            : $type->unit;
    }

    public function isLegacyEdit(Request $request, ResearchType $type): bool
    {
        $submission = $request->route('submission');

        return $submission instanceof ResearchSubmission && $submission->research_type === $type->slug
            && $submission->unit_snapshot === null && $request->input('quantity') === null;
    }

    public function calculate(Request $request, ResearchType $type): array
    {
        $submission = $request->route('submission');
        $sameSnapshot = $submission instanceof ResearchSubmission && $submission->research_type === $type->slug && $submission->unit_snapshot !== null;
        $rate = $sameSnapshot ? $submission->rate_snapshot : $type->pointSetting?->points_per_output;
        if ($rate === null) {
            throw ValidationException::withMessages(['research_type' => 'The administrator has not configured points for this research type yet.']);
        }

        $legacy = $this->isLegacyEdit($request, $type);
        $unit = $this->unit($request, $type);
        $quantity = ! $legacy && $unit !== 'output' ? (float) $request->input('quantity') : null;
        $points = $legacy && $submission->claimed_points !== null
            ? (float) $submission->claimed_points
            : round((float) $rate * ($quantity ?? 1), 2);
        if ($points > 999999.99) {
            throw ValidationException::withMessages(['quantity' => 'The calculated points cannot exceed 999999.99.']);
        }

        return [
            'claimed_points' => $points,
            'quantity' => $quantity,
            'unit_snapshot' => $legacy ? null : $unit,
            'rate_snapshot' => $legacy ? null : $rate,
        ];
    }
}
