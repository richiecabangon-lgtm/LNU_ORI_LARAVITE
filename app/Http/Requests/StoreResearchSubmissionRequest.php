<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CreditsSoleParticipant;
use App\Models\ResearchSubmission;
use App\Models\ResearchType;
use App\Services\ResearchSubmissionDuplicateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreResearchSubmissionRequest extends FormRequest
{
    use CreditsSoleParticipant;

    public function authorize(): bool
    {
        return $this->user()?->can('create', ResearchSubmission::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'research_type' => ['required', Rule::exists('research_types', 'slug')->where('is_active', true)],
            'quantity' => $this->quantityRules(),
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'abstract' => ['nullable', 'string', 'max:5000'],
            'keywords' => ['nullable', 'string', 'max:2000'],
            'research_interests' => ['sometimes', 'nullable', 'array', 'list', 'max:5'],
            'research_interests.*' => ['required', 'string', 'max:255'],
            'funding_source' => ['nullable', Rule::in(['personal', 'internal', 'external'])],
            'funding_organization' => ['nullable', 'required_if:funding_source,external', 'prohibited_unless:funding_source,external', 'string', 'max:255'],
            'authors' => ['required', 'array', 'min:1'],
            'authors.*' => ['required', 'string', 'max:255'],
            'author_details' => ['required', 'array', 'min:1', 'max:20'],
            'author_details.*.name' => ['required', 'string', 'max:255'],
            'author_details.*.email' => ['nullable', 'email', 'max:255'],
            'author_details.*.institution' => ['nullable', 'string', 'max:255'],
            'author_details.*.allocated_points' => ['required', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
            'author_details.*.authorization_file' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
            'sdgs' => ['nullable', 'array', 'max:17'],
            'sdgs.*' => ['required', 'integer', Rule::exists('sdgs', 'id')->where('is_active', true)],
            'research_agendas' => ['nullable', 'array', 'max:100'],
            'research_agendas.*' => ['required', 'integer', Rule::exists('research_agendas', 'id')->where('is_active', true)],
            'venue' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'doi' => ['nullable', 'string', 'max:255'],
            'issn_isbn' => ['nullable', 'string', 'max:100'],
            'indexing' => ['nullable', 'string', 'max:255'],
            'publication_date' => ['nullable', 'date'],
            'faculty_remarks' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp'],
            'attachment_requirements' => ['nullable', 'array'],
            'attachment_requirements.*' => ['nullable', 'integer', Rule::exists('research_evidence_requirements', 'id')->where('research_type_id', ResearchType::where('slug', $this->input('research_type'))->value('id'))->where('is_active', true)],
            'submit_now' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['title', 'research_type'])) {
                return;
            }

            $message = app(ResearchSubmissionDuplicateService::class)->message(
                (string) $this->input('title'), (string) $this->input('research_type'), $this->user(),
            );

            if ($message !== null) {
                $validator->errors()->add('title', $message);
            }
        }];
    }
}
