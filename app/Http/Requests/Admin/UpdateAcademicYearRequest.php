<?php

namespace App\Http\Requests\Admin;

use App\Enums\AcademicYearStatus;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Models\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicYearRequest extends FormRequest
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
        /** @var AcademicYear $academicYear */
        $academicYear = $this->route('academicYear');

        return [
            'code' => [
                'required', 'string', 'max:20', 'regex:/^[0-9]{4}\/[0-9]{4}$/',
                Rule::unique('academic_years', 'code')->ignore($academicYear->getKey()),
            ],
            'label' => ['required', 'string', 'max:50'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'status' => ['required', Rule::enum(AcademicYearStatus::class)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'kode tahun ajaran',
            'label' => 'label tahun ajaran',
            'starts_on' => 'tanggal mulai',
            'ends_on' => 'tanggal selesai',
            'status' => 'status',
            'description' => 'deskripsi',
        ];
    }
}
