<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentGuardianRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('student')) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:150'],
            'relationship_type' => ['required', Rule::in([
                'father', 'mother', 'grandfather', 'grandmother', 'sibling', 'uncle', 'aunt', 'other',
            ])],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:2000'],
            'national_id' => ['nullable', 'string', 'max:50', 'regex:/^[0-9]{8,32}$/'],
            'is_primary_contact' => ['nullable', 'boolean'],
            'is_emergency_contact' => ['nullable', 'boolean'],
            'priority_order' => ['nullable', 'integer', 'min:1', 'max:20'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'relationship_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'legal_name' => 'nama wali',
            'relationship_type' => 'hubungan',
            'phone' => 'telepon wali',
            'email' => 'email wali',
            'address' => 'alamat wali',
            'national_id' => 'nomor identitas wali',
            'priority_order' => 'urutan',
            'starts_on' => 'tanggal mulai',
            'ends_on' => 'tanggal selesai',
            'relationship_note' => 'catatan hubungan',
        ];
    }
}
