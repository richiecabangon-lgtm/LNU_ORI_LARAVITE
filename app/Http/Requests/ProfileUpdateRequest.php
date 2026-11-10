<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
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
}
