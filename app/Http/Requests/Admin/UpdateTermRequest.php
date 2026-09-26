<?php

namespace App\Http\Requests\Admin;

use App\Enums\TermStatus;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Http\Requests\Concerns\ValidatesSemesterDates;
use App\Models\Term;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Updating one semester.
 *
 * This request used to be the reason a single academic year could not hold
 * Semester 1 and Semester 2: the composite uniqueness rule was attached to
 * `academic_year_id` instead of `sequence`, so editing the second semester of a
 * year was rejected with "academic year value already used" whenever that year's
 * id happened to equal an existing sequence number in the same year — and a
 * sequence that genuinely did collide reached the database and surfaced as a 500.
 *
 * The rule now sits on `sequence`, scoped to the year being saved, and ignores
 * the row being edited.
 */
class UpdateTermRequest extends FormRequest
{
    use HasIndonesianValidationMessages;
    use ValidatesSemesterDates;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('term')) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Term $term */
        $term = $this->route('term');

        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'sequence' => [
                'required', 'integer', 'min:1', 'max:20',
                Rule::unique('terms', 'sequence')
                    ->where(fn ($query) => $query->where('academic_year_id', $this->input('academic_year_id')))
                    ->ignore($term->getKey()),
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
        /** @var Term $term */
        $term = $this->route('term');

        return [
            $this->assertSemesterDates($term),
            function (Validator $validator) use ($term): void {
                /*
                 * Moving a semester to a different academic year must not leave
                 * its teaching assignments pointing at a year the term no longer
                 * belongs to.
                 */
                $targetYearId = (int) $this->input('academic_year_id');

                if ($targetYearId === (int) $term->academic_year_id) {
                    return;
                }

                $mismatched = $term->teachingAssignments()
                    ->where('academic_year_id', '!=', $targetYearId)
                    ->exists();

                if ($mismatched) {
                    $validator->errors()->add(
                        'academic_year_id',
                        'Semester masih memiliki assignment mengajar dari tahun ajaran lain.',
                    );
                }
            },
        ];
    }
}
