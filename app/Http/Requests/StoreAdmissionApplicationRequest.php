<?php

namespace App\Http\Requests;

use App\Enums\GuardianRelationshipType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdmissionApplicationRequest extends FormRequest
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
            'admission_period_id' => ['required', 'integer', 'exists:admission_periods,id'],
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
            'website' => ['nullable', 'string', 'max:0'],
            'guardians' => ['required', 'array', 'min:1', 'max:5'],
            'guardians.*.legal_name' => ['required', 'string', 'max:150'],
            'guardians.*.relationship_type' => ['required', Rule::enum(GuardianRelationshipType::class)],
            'guardians.*.phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{8,30}$/'],
            'guardians.*.email' => ['nullable', 'email', 'max:150'],
            'guardians.*.address' => ['nullable', 'string', 'max:2000'],
            'guardians.*.is_primary_contact' => ['required', 'boolean'],
            'guardians.*.is_emergency_contact' => ['required', 'boolean'],
            'guardians.*.priority_order' => ['nullable', 'integer', 'between:1,99'],
        ];
    }
}
