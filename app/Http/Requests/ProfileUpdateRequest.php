<?php

namespace App\Http\Requests;

use App\Models\College;
use App\Models\Department;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'designation' => ['sometimes', 'nullable', 'string', 'max:255'],
            ...($this->user()->isFaculty() ? [
                'college_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'exists:colleges,id'],
                'department_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'exists:departments,id'],
            ] : [
                'college_id' => ['missing'],
                'department_id' => ['missing'],
            ]),
            'academic_rank' => ['nullable', 'string', 'max:100'],
            'orcid_url' => ['nullable', 'url', 'max:255'],
            'google_scholar_url' => ['nullable', 'url', 'max:255'],
            'scopus_url' => [
                'nullable', 'bail', 'string', 'url:http,https', 'max:255',
                function (string $attribute, string $value, \Closure $fail): void {
                    $parts = parse_url($value);

                    if ($parts === false || ! in_array(strtolower($parts['host'] ?? ''), ['scopus.com', 'www.scopus.com'], true)
                        || isset($parts['user']) || isset($parts['pass'])) {
                        $fail('The Scopus profile must be a URL on scopus.com.');
                    }
                },
            ],
            'institutional_profile_url' => ['nullable', 'url', 'max:255'],
            'research_interests' => ['sometimes', 'nullable', 'array', 'list', 'max:5'],
            'research_interests.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $user = $this->user();
            $input = $this->all();

            if (! $user->isFaculty() || (! array_key_exists('college_id', $input) && ! array_key_exists('department_id', $input))
                || $validator->errors()->hasAny(['college_id', 'department_id'])) {
                return;
            }

            $collegeId = array_key_exists('college_id', $input) ? $input['college_id'] : $user->college_id;
            $departmentId = array_key_exists('department_id', $input) ? $input['department_id'] : $user->department_id;
            $college = $collegeId === null ? null : College::query()->find($collegeId);
            $department = $departmentId === null ? null : Department::query()->find($departmentId);

            if ($college && ! $college->is_active && (int) $collegeId !== (int) $user->college_id) {
                $validator->errors()->add('college_id', 'The selected college is inactive.');
            }

            if ($department && (int) $department->college_id !== (int) $collegeId) {
                $validator->errors()->add('department_id', 'The selected department must belong to the selected college.');
            } elseif ($department && (! $department->is_active || ! $college?->is_active)
                && (int) $departmentId !== (int) $user->department_id) {
                $validator->errors()->add('department_id', 'The selected department is inactive.');
            }
        }];
    }
}
