<?php

namespace App\Http\Requests\Admin;

use App\Enums\TeacherEmploymentStatus;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Models\Teacher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('teacher')) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Teacher $teacher */
        $teacher = $this->route('teacher');

        return [
            'legal_name' => ['required', 'string', 'max:150'],
            'preferred_name' => ['nullable', 'string', 'max:150'],
            'employee_number' => [
                'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._\/-]+$/',
                Rule::unique('teachers', 'employee_number')->ignore($teacher->getKey()),
            ],
            'employment_status' => ['required', Rule::enum(TeacherEmploymentStatus::class)],
            'employment_start_date' => ['required', 'date'],
            'employment_end_date' => ['nullable', 'date', 'after_or_equal:employment_start_date'],
            'position_title' => ['nullable', 'string', 'max:100'],
            'qualification_summary' => ['nullable', 'string', 'max:2000'],
            'biography' => ['nullable', 'string', 'max:5000'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'national_id' => ['nullable', 'string', 'max:50', 'regex:/^[0-9]{8,32}$/'],
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
            'employee_number' => 'nomor induk pegawai',
            'employment_status' => 'status kepegawaian',
            'employment_start_date' => 'tanggal mulai kerja',
            'employment_end_date' => 'tanggal akhir kerja',
            'position_title' => 'jabatan',
            'qualification_summary' => 'ringkasan kualifikasi',
            'biography' => 'biografi',
            'birth_date' => 'tanggal lahir',
            'birth_place' => 'tempat lahir',
            'gender' => 'jenis kelamin',
            'national_id' => 'nomor identitas nasional',
            'address' => 'alamat',
            'phone' => 'telepon',
            'email' => 'email',
        ];
    }
}
