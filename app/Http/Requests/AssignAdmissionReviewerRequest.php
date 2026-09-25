<?php

namespace App\Http\Requests;

use App\Models\AdmissionApplication;
use Illuminate\Foundation\Http\FormRequest;

class AssignAdmissionReviewerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('admissionApplication');

        return $application instanceof AdmissionApplication && ($this->user()?->can('assignReviewer', $application) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reviewer_user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
