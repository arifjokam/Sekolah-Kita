<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitAdmissionCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'access_token' => ['required', 'string', 'max:128'],
            'applicant_name' => ['required', 'string', 'max:150'],
            'preferred_name' => ['nullable', 'string', 'max:150'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'contact_email' => ['required', 'email', 'max:150'],
            'contact_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{8,30}$/'],
            'address' => ['nullable', 'string', 'max:2000'],
            'current_school' => ['nullable', 'string', 'max:150'],
            'requested_grade_level' => ['nullable', 'integer', 'between:1,12'],
            'consent' => ['accepted'],
        ];
    }
}
