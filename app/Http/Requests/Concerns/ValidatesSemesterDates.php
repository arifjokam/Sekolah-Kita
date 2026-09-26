<?php

namespace App\Http\Requests\Concerns;

use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Contracts\Validation\Validator;

/**
 * The date rules that make a semester coherent.
 *
 * A term is one slice of an academic year, so:
 *
 *  1. it must sit inside the year it belongs to; and
 *  2. it must not overlap another term of the same year.
 *
 * Without (1) a "Semester 2" can be dated inside the following year, and without
 * (2) a school can end up with two overlapping semesters and no way to tell which
 * one a teaching assignment belongs to. Both were unreachable before, because the
 * only existing check was "ends_on is after starts_on".
 */
trait ValidatesSemesterDates
{
    protected function assertSemesterDates(?Term $term = null): \Closure
    {
        return function (Validator $validator) use ($term): void {
            $yearId = (int) $this->input('academic_year_id');
            $startsOn = (string) $this->input('starts_on');
            $endsOn = (string) $this->input('ends_on');

            if ($yearId <= 0 || $startsOn === '' || $endsOn === '') {
                return;
            }

            $year = AcademicYear::query()->find($yearId);

            if ($year !== null
                && ($startsOn < $year->starts_on->toDateString() || $endsOn > $year->ends_on->toDateString())) {
                $validator->errors()->add(
                    'starts_on',
                    'Periode semester harus berada di dalam tahun ajaran '.$year->label.'.',
                );

                return;
            }

            $overlaps = Term::query()
                ->where('academic_year_id', $yearId)
                ->when($term instanceof Term, fn ($query) => $query->whereKeyNot($term->getKey()))
                // Touching boundaries are not an overlap: Semester 1 ending on the
                // 31st and Semester 2 starting on the 1st is the normal case.
                ->where('starts_on', '<=', $endsOn)
                ->where('ends_on', '>=', $startsOn)
                ->exists();

            if ($overlaps) {
                $validator->errors()->add(
                    'starts_on',
                    'Periode semester ini bertumpuk dengan semester lain pada tahun ajaran yang sama.',
                );
            }
        };
    }
}
