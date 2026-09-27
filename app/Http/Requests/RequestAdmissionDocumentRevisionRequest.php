<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Models\ApplicationDocument;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sending a document back for a replacement.
 *
 * The reason is mandatory here, unlike on the combined status form: a candidate
 * who is asked to re-upload a file has to be told what was wrong with it, and
 * "perbaiki" on its own is not actionable.
 */
class RequestAdmissionDocumentRevisionRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof ApplicationDocument
            && ($this->user()?->can('requestRevision', $document) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'reviewer_note' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reviewer_note' => 'alasan perbaikan'];
    }
}
