<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class TransferEnrollmentRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        return $this->user()?->can('transfer', $this->route('enrollment')) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'effective_on' => ['required', 'date'],
            'transition_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'class_id' => 'kelas tujuan',
            'effective_on' => 'tanggal berlaku',
            'transition_reason' => 'alasan perpindahan',
        ];
    }
}
