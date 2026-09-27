<?php

namespace App\Http\Requests\Ppdb;

use App\Enums\AdmissionDocumentType;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Models\AdmissionApplication;
use App\Models\ApplicationDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Uploading a supporting document from the applicant portal.
 *
 * The file rules mirror PrivateDocumentService: only PDF/JPG/PNG by extension,
 * only by declared MIME, and only up to the configured byte ceiling. The
 * controller additionally runs a content check, because a MIME header is a claim
 * rather than a fact.
 */
class UploadApplicantDocumentRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $document = $this->route('document');

        if ($document instanceof ApplicationDocument) {
            return $user->can('replaceOwn', $document);
        }

        $application = $this->route('application');

        return $application instanceof AdmissionApplication
            && ($user->can('viewOwn', $application) || $user->can('review', $application));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $maxKilobytes = (int) ceil(((int) config('sekolah-kita.ppdb.max_document_bytes', 10_485_760)) / 1024);

        return [
            // The category is the applicant's choice for a first upload, and the
            // document's own category for a replacement — so it is only required
            // on the create route.
            'document_type' => [Rule::requiredIf($this->isCreating()), Rule::enum(AdmissionDocumentType::class)],
            'document' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:'.$maxKilobytes,
            ],
        ];
    }

    private function isCreating(): bool
    {
        return ! $this->route('document') instanceof ApplicationDocument;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->indonesianMessages(), [
            'document.mimes' => 'Berkas harus berformat PDF, JPG, atau PNG.',
            'document.max' => 'Ukuran berkas melebihi batas maksimum yang diizinkan.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'document_type' => 'jenis dokumen',
            'document' => 'berkas',
        ];
    }
}
