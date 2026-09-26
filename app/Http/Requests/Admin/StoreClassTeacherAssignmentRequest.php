<?php

namespace App\Http\Requests\Admin;

use App\Enums\AssignmentStatus;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Models\ClassTeacherAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassTeacherAssignmentRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        return $this->user()?->can('create', ClassTeacherAssignment::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
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
            'class_id' => 'kelas',
            'teacher_id' => 'guru',
            'status' => 'status',
            'starts_on' => 'tanggal mulai',
            'ends_on' => 'tanggal selesai',
            'internal_reason' => 'catatan internal',
        ];
    }
}
