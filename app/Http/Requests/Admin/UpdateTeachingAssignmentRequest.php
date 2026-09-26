<?php

namespace App\Http\Requests\Admin;

use App\Enums\AssignmentStatus;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeachingAssignmentRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('teachingAssignment')) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'term_id' => [
                'nullable', 'integer', 'exists:terms,id',
                Rule::exists('terms', 'id')->where('academic_year_id', $this->input('academic_year_id')),
            ],
            'status' => ['required', Rule::enum(AssignmentStatus::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'internal_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'teacher_id' => 'guru',
            'class_id' => 'kelas',
            'subject_id' => 'mata pelajaran',
            'academic_year_id' => 'tahun ajaran',
            'term_id' => 'term',
            'status' => 'status',
            'starts_on' => 'tanggal mulai',
            'ends_on' => 'tanggal selesai',
            'internal_reason' => 'catatan internal',
        ];
    }
}
