<?php

namespace App\Http\Requests;

use App\Enums\AdmissionDocumentVerificationStatus;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Models\ApplicationDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A reviewer's decision on one supporting document.
 *
 * Three states, not two. `needs_revision` is the one this form used to be
 * missing: without it a reviewer could only accept a file or reject it, and an
 * unreadable scan had to be recorded as a final rejection instead of a request to
 * upload a better copy.
 */
class UpdateAdmissionDocumentStatusRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof ApplicationDocument
            && ($this->user()?->can('verify', $document) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(AdmissionDocumentVerificationStatus::reviewerOptions()))],
            'reviewer_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->indonesianMessages(), [
            'status.in' => 'Status dokumen tidak valid.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => 'status dokumen',
            'reviewer_note' => 'catatan reviewer',
        ];
    }
}
