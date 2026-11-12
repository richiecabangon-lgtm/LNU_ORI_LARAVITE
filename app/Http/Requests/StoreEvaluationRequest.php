<?php

namespace App\Http\Requests;

use App\Enums\EvaluationRecommendation;
use App\Models\ResearchTypePointSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('submission');

        return $this->user()?->can('evaluate', $submission) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $submission = $this->route('submission');
        $pointsLimit = $submission->unit_snapshot !== null ? $submission->claimed_points : (ResearchTypePointSetting::query()
            ->where('research_type', $submission->research_type)
            ->value('points_per_output') ?? 999999.99);

        return [
            'score' => ['required', 'numeric', 'min:0', 'max:100'],
            'quality_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'relevance_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'documentation_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review_confirmed' => ['accepted'],
            'recommendation' => ['required', Rule::enum(EvaluationRecommendation::class)],
            'awarded_points' => ['nullable', 'numeric', 'min:0', 'max:'.$pointsLimit],
            'remarks' => ['required', 'string', 'max:5000'],
        ];
    }
}
