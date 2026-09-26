<?php

namespace App\Http\Requests\Admin;

use App\Enums\ScheduleStatus;
use App\Enums\WeekDay;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Models\ClassSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating or updating one weekly lesson.
 *
 * Only the shape is validated here. The three conflict rules (class, teacher and
 * room) and the cross-scope integrity rules (the semester must belong to the
 * class's year, the teacher must actually be assigned) live in
 * ScheduleAdministrationService, so they cannot be bypassed by a request that
 * does not go through this form.
 */
class ClassScheduleRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        $schedule = $this->route('classSchedule');

        return $schedule instanceof ClassSchedule
            ? ($this->user()?->can('update', $schedule) ?? false)
            : ($this->user()?->can('create', ClassSchedule::class) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'day_of_week' => ['required', 'integer', Rule::in(array_keys(WeekDay::options()))],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'room' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::enum(ScheduleStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->indonesianMessages(), [
            'starts_at.date_format' => 'Waktu mulai harus berformat HH:MM.',
            'ends_at.date_format' => 'Waktu selesai harus berformat HH:MM.',
            'ends_at.after' => 'Waktu selesai harus setelah waktu mulai.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'academic_year_id' => 'tahun ajaran',
            'term_id' => 'semester',
            'class_id' => 'kelas',
            'subject_id' => 'mata pelajaran',
            'teacher_id' => 'guru',
            'day_of_week' => 'hari',
            'starts_at' => 'waktu mulai',
            'ends_at' => 'waktu selesai',
            'room' => 'ruang',
            'status' => 'status',
        ];
    }
}
