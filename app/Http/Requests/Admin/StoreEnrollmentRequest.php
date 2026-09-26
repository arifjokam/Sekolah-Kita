<?php

namespace App\Http\Requests\Admin;

use App\Enums\EnrollmentStatus;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnrollmentRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Enrollment::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'student_id' => [
                'required', 'integer', 'exists:students,id',
                Rule::unique('enrollments', 'student_id')
                    ->where('status', EnrollmentStatus::Active->value),
            ],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'enrolled_on' => ['required', 'date'],
            'effective_from' => ['nullable', 'date', 'after_or_equal:enrolled_on'],
            'transition_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'student_id' => 'siswa',
            'class_id' => 'kelas',
            'enrolled_on' => 'tanggal enrollment',
            'effective_from' => 'tanggal berlaku',
            'transition_reason' => 'alasan',
        ];
    }
}
