<?php

namespace App\Http\Requests;

use App\Enums\AdmissionDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadAdmissionDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'mimes:pdf,jpeg,jpg,png', 'max:'.((int) config('sekolah-kita.ppdb.max_document_bytes', 10_485_760) / 1024)],
            'document_type' => ['required', Rule::enum(AdmissionDocumentType::class)],
            'access_token' => ['nullable', 'string', 'max:128'],
        ];
    }
}
