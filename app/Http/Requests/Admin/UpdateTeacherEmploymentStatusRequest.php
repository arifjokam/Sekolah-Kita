<?php

namespace App\Http\Requests\Admin;

use App\Enums\TeacherEmploymentStatus;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherEmploymentStatusRequest extends FormRequest
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
        return [
            'employment_status' => ['required', Rule::enum(TeacherEmploymentStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'employment_status' => 'status kepegawaian',
        ];
    }
}
