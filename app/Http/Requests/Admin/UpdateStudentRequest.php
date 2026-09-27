<?php

namespace App\Http\Requests\Admin;

use App\Enums\StudentLifecycleStatus;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
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
        /** @var Student $student */
        $student = $this->route('student');

        return [
            'legal_name' => ['required', 'string', 'max:150'],
            'preferred_name' => ['nullable', 'string', 'max:150'],
            'student_number' => [
                'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._\/-]+$/',
                Rule::unique('students', 'student_number')->ignore($student->getKey()),
            ],
            'status' => ['required', Rule::enum(StudentLifecycleStatus::class)],
            'admission_date' => ['required', 'date'],
            'graduation_date' => ['nullable', 'date', 'after_or_equal:admission_date'],
            'leaving_date' => ['nullable', 'date', 'after_or_equal:admission_date'],
            'leaving_reason' => ['nullable', 'string', 'max:1000'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'national_id' => ['nullable', 'string', 'max:50', 'regex:/^[0-9]{8,32}$/'],
            'nisn' => ['nullable', 'string', 'max:50', 'regex:/^[0-9]{8,20}$/'],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:150'],
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
            'graduation_date' => 'tanggal lulus',
            'leaving_date' => 'tanggal keluar',
            'leaving_reason' => 'alasan keluar',
            'birth_date' => 'tanggal lahir',
            'birth_place' => 'tempat lahir',
            'gender' => 'jenis kelamin',
            'national_id' => 'nomor identitas nasional',
            'nisn' => 'NISN',
            'address' => 'alamat',
            'phone' => 'telepon',
            'email' => 'email',
        ];
    }
}
