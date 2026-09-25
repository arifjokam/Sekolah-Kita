<?php

namespace App\Http\Requests;

use App\Models\AdmissionPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdmissionPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $period = $this->route('admissionPeriod');

        return $period instanceof AdmissionPeriod && ($this->user()?->can('update', $period) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $period = $this->route('admissionPeriod');

        return [
            'academic_year_id' => ['sometimes', 'integer', 'exists:academic_years,id'],
            'name' => ['sometimes', 'string', 'max:150'],
            'slug' => ['sometimes', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('admission_periods', 'slug')->ignore($period?->getKey())],
            'description' => ['nullable', 'string', 'max:5000'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'opens_at' => ['sometimes', 'date'],
            'closes_at' => ['sometimes', 'date', 'after:opens_at'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
