<?php

namespace App\Http\Requests;

use App\Models\ApplicationDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdmissionDocumentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof ApplicationDocument && ($this->user()?->can('verify', $document) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['verified', 'rejected'])],
            'reviewer_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
