<?php

namespace App\Http\Requests\Admin;

use App\Enums\AcademicYearStatus;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicYearStatusRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('academicYear')) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AcademicYearStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['status' => 'status tahun ajaran'];
    }
}
