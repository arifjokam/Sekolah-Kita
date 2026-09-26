<?php

namespace App\Http\Requests\Admin;

use App\Enums\TermStatus;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Models\Term;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTermRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Term::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'sequence' => [
                'required', 'integer', 'min:1', 'max:20',
                // terms has a unique (academic_year_id, sequence) index, so the
                // form has to reject the duplicate before the insert.
                Rule::unique('terms', 'sequence')->where('academic_year_id', $this->input('academic_year_id')),
            ],
            'label' => ['required', 'string', 'max:50'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'status' => ['required', Rule::enum(TermStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'academic_year_id' => 'tahun ajaran',
            'sequence' => 'urutan',
            'label' => 'label term',
            'starts_on' => 'tanggal mulai',
            'ends_on' => 'tanggal selesai',
            'status' => 'status',
        ];
    }
}
