<?php

namespace App\Http\Requests;

use App\Models\ResearchTypePointSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('submission');

        return $this->user()?->can('approve', $submission) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $submission = $this->route('submission');
        $pointsLimit = ResearchTypePointSetting::query()
            ->where('research_type', $submission->research_type)
            ->value('points_per_output') ?? 999999.99;

        return [
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'final_points' => ['nullable', 'numeric', 'min:0', 'max:'.$pointsLimit],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
