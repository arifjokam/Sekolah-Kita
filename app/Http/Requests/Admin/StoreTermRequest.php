<?php

namespace App\Http\Requests\Admin;

use App\Enums\TermStatus;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Http\Requests\Concerns\ValidatesSemesterDates;
use App\Models\Term;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating one semester inside an academic year.
 *
 * The uniqueness rule belongs on `sequence`, scoped to the academic year — that
 * is exactly what the terms_academic_year_id_sequence_unique index enforces, so
 * the form and the database agree, and Semester 1 plus Semester 2 in one year is
 * allowed while a repeated sequence in the same year is not.
 */
class StoreTermRequest extends FormRequest
{
    use HasIndonesianValidationMessages;
    use ValidatesSemesterDates;

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
                Rule::unique('terms', 'sequence')->where(
                    fn ($query) => $query->where('academic_year_id', $this->input('academic_year_id')),
                ),
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
            'sequence' => 'urutan semester',
            'label' => 'label semester',
            'starts_on' => 'tanggal mulai',
            'ends_on' => 'tanggal selesai',
            'status' => 'status',
        ];
    }

    public function after(): array
    {
        return [$this->assertSemesterDates()];
    }
}
