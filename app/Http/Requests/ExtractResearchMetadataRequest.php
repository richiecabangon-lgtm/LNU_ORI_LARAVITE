<?php

namespace App\Http\Requests;

use App\Models\ResearchSubmission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExtractResearchMetadataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ResearchSubmission::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'paper' => ['required', 'file', 'max:10240', 'mimes:pdf,docx,jpg,jpeg,png,webp'],
        ];
    }
}
