<?php

namespace App\Http\Requests\Concerns;

use App\Models\ResearchType;
use Illuminate\Validation\Rule;

trait ValidatesActivityDates
{
    protected function activityDateRules(): array
    {
        $type = ResearchType::query()->active()->where('slug', $this->input('research_type'))->first();
        $mode = $this->input('activity_dates.mode');
        $allowedKeys = match ($mode) {
            'single' => 'mode,date',
            'range' => 'mode,start,end',
            'multiple' => 'mode,dates',
            default => 'mode',
        };

        return [
            'activity_dates' => ! $type || $type->has_authors
                ? ['missing']
                : ['sometimes', 'nullable', 'array:'.$allowedKeys, 'min:1'],
            'activity_dates.mode' => ['required_with:activity_dates', Rule::in(['single', 'range', 'multiple'])],
            'activity_dates.date' => ['required_if:activity_dates.mode,single', 'string', 'date_format:Y-m-d'],
            'activity_dates.start' => ['required_if:activity_dates.mode,range', 'string', 'date_format:Y-m-d'],
            'activity_dates.end' => ['required_if:activity_dates.mode,range', 'string', 'date_format:Y-m-d', 'after_or_equal:activity_dates.start'],
            'activity_dates.dates' => ['required_if:activity_dates.mode,multiple', 'array', 'list', 'min:2', 'max:50'],
            'activity_dates.dates.*' => ['required', 'string', 'date_format:Y-m-d', 'distinct:strict'],
        ];
    }
}
