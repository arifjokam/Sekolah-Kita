<?php

namespace App\Http\Requests;

use App\Models\AdmissionApplication;
use Illuminate\Foundation\Http\FormRequest;

class ReviewAdmissionApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('admissionApplication');

        return $application instanceof AdmissionApplication && ($this->user()?->can('review', $application) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'internal_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
