<?php

namespace App\Http\Requests\Admin;

use App\Enums\ClassStatus;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Models\ClassModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        return $this->user()?->can('create', ClassModel::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'grade_level' => ['required', 'integer', 'min:1', 'max:13'],
            'name' => [
                'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9 .\-]+$/',
                Rule::unique('classes', 'name')
                    ->where('academic_year_id', $this->input('academic_year_id'))
                    ->where('grade_level', $this->input('grade_level')),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'status' => ['required', Rule::enum(ClassStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'academic_year_id' => 'tahun ajaran',
            'grade_level' => 'tingkat',
            'name' => 'nama kelas',
            'capacity' => 'kapasitas',
            'status' => 'status',
        ];
    }
}
