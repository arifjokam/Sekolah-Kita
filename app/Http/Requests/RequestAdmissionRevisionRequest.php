<?php

namespace App\Http\Requests;

use App\Models\AdmissionApplication;
use Illuminate\Foundation\Http\FormRequest;

class RequestAdmissionRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('admissionApplication');

        return $application instanceof AdmissionApplication && ($this->user()?->can('requestRevision', $application) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'public_message' => ['required', 'string', 'max:255'],
            'deadline' => ['nullable', 'date', 'after:now'],
        ];
    }
}
