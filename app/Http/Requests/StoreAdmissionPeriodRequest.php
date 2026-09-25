<?php

namespace App\Http\Requests;

use App\Models\AdmissionPeriod;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdmissionPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AdmissionPeriod::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:admission_periods,slug'],
            'description' => ['nullable', 'string', 'max:5000'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
