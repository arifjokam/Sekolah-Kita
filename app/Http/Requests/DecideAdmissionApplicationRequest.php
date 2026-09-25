<?php

namespace App\Http\Requests;

use App\Enums\AdmissionReviewDecision;
use App\Models\AdmissionApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideAdmissionApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('admissionApplication');

        return $application instanceof AdmissionApplication && ($this->user()?->can('decide', $application) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(AdmissionReviewDecision::class)],
            'target_class_id' => [
                Rule::requiredIf($this->input('decision') === AdmissionReviewDecision::Accept->value),
                'nullable',
                'integer',
                'exists:classes,id',
            ],
            'internal_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
