<?php

namespace App\Http\Requests\Admin;

use App\Enums\EnrollmentStatus;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnrollmentRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('enrollment')) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Enrollment $enrollment */
        $enrollment = $this->route('enrollment');

        return [
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'status' => ['required', Rule::enum(EnrollmentStatus::class)],
            'enrolled_on' => ['required', 'date'],
            'effective_from' => ['nullable', 'date', 'after_or_equal:enrolled_on'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'completed_on' => ['nullable', 'date', 'after_or_equal:enrolled_on'],
            'transition_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'class_id' => 'kelas',
            'status' => 'status enrollment',
            'enrolled_on' => 'tanggal enrollment',
            'effective_from' => 'tanggal mulai berlaku',
            'effective_to' => 'tanggal selesai berlaku',
            'completed_on' => 'tanggal selesai',
            'transition_reason' => 'alasan',
        ];
    }
}
