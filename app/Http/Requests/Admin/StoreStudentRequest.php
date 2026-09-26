<?php

namespace App\Http\Requests\Admin;

use App\Enums\StudentLifecycleStatus;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Student::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:150'],
            'preferred_name' => ['nullable', 'string', 'max:150'],
            'student_number' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._\/-]+$/', Rule::unique('students', 'student_number')],
            'status' => ['required', Rule::enum(StudentLifecycleStatus::class)],
            'admission_date' => ['required', 'date'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'national_id' => ['nullable', 'string', 'max:50', 'regex:/^[0-9]{8,32}$/'],
            'nisn' => ['nullable', 'string', 'max:50', 'regex:/^[0-9]{8,20}$/'],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:150'],

            'enrollment.class_id' => ['nullable', 'required_with:has_enrollment', 'integer', 'exists:classes,id'],
            'enrollment.enrolled_on' => ['nullable', 'required_with:has_enrollment', 'date'],
            'enrollment.effective_from' => ['nullable', 'date', 'after_or_equal:enrollment.enrolled_on'],
            'enrollment.transition_reason' => ['nullable', 'string', 'max:1000'],

            'guardian.legal_name' => ['nullable', 'required_with:has_guardian', 'string', 'max:150'],
            'guardian.relationship_type' => ['nullable', Rule::in([
                'father', 'mother', 'grandfather', 'grandmother', 'sibling', 'uncle', 'aunt', 'other',
            ])],
            'guardian.phone' => ['nullable', 'string', 'max:30'],
            'guardian.email' => ['nullable', 'string', 'email', 'max:150'],
            'guardian.address' => ['nullable', 'string', 'max:2000'],
            'guardian.national_id' => ['nullable', 'string', 'max:50', 'regex:/^[0-9]{8,32}$/'],
            'guardian.is_primary_contact' => ['nullable', 'boolean'],
            'guardian.is_emergency_contact' => ['nullable', 'boolean'],
            'guardian.priority_order' => ['nullable', 'integer', 'min:1', 'max:20'],
            'guardian.starts_on' => ['nullable', 'date'],
            'guardian.relationship_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'legal_name' => 'nama lengkap',
            'preferred_name' => 'nama panggilan',
            'student_number' => 'nomor induk siswa',
            'status' => 'status siswa',
            'admission_date' => 'tanggal masuk',
            'birth_date' => 'tanggal lahir',
            'birth_place' => 'tempat lahir',
            'gender' => 'jenis kelamin',
            'national_id' => 'nomor identitas nasional',
            'nisn' => 'NISN',
            'address' => 'alamat',
            'phone' => 'telepon',
            'email' => 'email',
            'enrollment.class_id' => 'kelas',
            'enrollment.enrolled_on' => 'tanggal Enrollment',
            'enrollment.effective_from' => 'tanggal berlaku',
            'guardian.legal_name' => 'nama wali',
            'guardian.relationship_type' => 'hubungan',
            'guardian.phone' => 'telepon wali',
            'guardian.email' => 'email wali',
            'guardian.national_id' => 'nomor identitas wali',
            'guardian.priority_order' => 'urutan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->baseMessages(), [
            'enrollment.class_id.required_with' => 'Pilih kelas bila enrollment ingin langsung dibuat.',
            'enrollment.enrolled_on.required_with' => 'Tanggal enrollment wajib diisi bila kelas dipilih.',
            'guardian.legal_name.required_with' => 'Nama wali wajib diisi bila data wali diisi.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function baseMessages(): array
    {
        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'required_with' => 'Kolom :attribute wajib diisi bersama :values.',
            'string' => 'Kolom :attribute harus berupa teks.',
            'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
            'date' => 'Kolom :attribute harus berupa tanggal yang valid.',
            'email' => 'Kolom :attribute harus berupa alamat email yang valid.',
            'max' => [
                'string' => 'Kolom :attribute maksimal :max karakter.',
                'numeric' => 'Kolom :attribute maksimal :max.',
            ],
            'min' => [
                'string' => 'Kolom :attribute minimal :min karakter.',
                'numeric' => 'Kolom :attribute minimal :min.',
            ],
            'exists' => 'Pilihan :attribute tidak valid atau sudah tidak tersedia.',
            'unique' => 'Nilai :attribute sudah digunakan.',
            'in' => 'Pilihan :attribute tidak valid.',
            'after_or_equal' => 'Kolom :attribute harus setelah atau sama dengan :date.',
            'before' => 'Kolom :attribute harus sebelum :date.',
            'boolean' => 'Kolom :attribute harus bernilai benar atau salah.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function enrollmentPayload(): ?array
    {
        if (! $this->boolean('has_enrollment')) {
            return null;
        }

        return $this->validated()['enrollment'];
    }

    /**
     * @return array<string, mixed>
     */
    public function guardianPayload(): ?array
    {
        if (! $this->boolean('has_guardian')) {
            return null;
        }

        return $this->validated()['guardian'];
    }
}
