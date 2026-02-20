<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();

    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // 1. Determine WHO is being edited.
        // If an Admin calls the route /api/user/{user}, get that ID.
        // If an Admin calls /api/coordinators/{coordinator}, get that ID.
        // If no route param exists, default to the logged-in user (Self-Update).

        $targetUser = $this->route('user') ?? $this->route('coordinator');
        $idToIgnore = $targetUser ? $targetUser->id : auth()->id();

        return [
            "fname" => ['required', 'string', 'max:255'],
            "lname" => ['required', 'string', 'max:255'],
            "mi" => ['string', 'nullable', 'max:1'],
            "suffix" => ['string', 'nullable', 'max:10'],
            "image_path" => ['string', 'nullable'],
            "college" => ['required', 'string', 'max:255'],
            "unit" => ['required', 'string', 'max:100'],
            "academic_rank" => ['required', 'string', 'max:100'],

            // 2. Ignore the ID of the person being edited
            "email" => ['required', 'string', 'unique:users,email,' . $idToIgnore],
        ];
    }
}
