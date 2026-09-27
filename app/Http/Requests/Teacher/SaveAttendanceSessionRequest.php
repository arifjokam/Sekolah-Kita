<?php

namespace App\Http\Requests\Teacher;

use App\Enums\AttendanceStatus;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Models\AttendanceSession;
use App\Services\AttendanceService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Saving an attendance sheet.
 *
 * `records` is keyed by student id. The policy inside authorize() is what stops
 * a teacher submitting marks for a class they have no authority over; this
 * request then guarantees every submitted id is on that class's roster, so a
 * tampered form cannot inject a student from another class.
 */
class SaveAttendanceSessionRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        $session = $this->route('session');

        if (! $session instanceof AttendanceSession) {
            return false;
        }

        return $this->isCorrection()
            ? ($this->user()?->can('correct', $session) ?? false)
            : ($this->user()?->can('submit', $session) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'records' => ['required', 'array', 'min:1'],
            'records.*' => ['array'],
            'records.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'records.*.note' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'records' => 'absensi',
            'note' => 'catatan pertemuan',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var AttendanceSession $session */
                $session = $this->route('session');
                $rosterIds = app(AttendanceService::class)
                    ->roster($session)
                    ->pluck('id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all();

                foreach (array_keys((array) $this->input('records', [])) as $studentId) {
                    if (! in_array((int) $studentId, $rosterIds, true)) {
                        $validator->errors()->add(
                            'records',
                            'Absensi memuat siswa yang tidak terdaftar di kelas ini.',
                        );

                        return;
                    }
                }

                foreach ((array) $this->input('records', []) as $studentId => $mark) {
                    if (! is_array($mark)) {
                        continue;
                    }

                    $status = AttendanceStatus::tryFrom((string) ($mark['status'] ?? ''));
                    $note = trim((string) ($mark['note'] ?? ''));

                    if ($status?->requiresNote() === true && $note === '') {
                        $validator->errors()->add(
                            'records.'.$studentId.'.note',
                            'Catatan wajib diisi untuk status '.$status->label().'.',
                        );
                    }
                }
            },
        ];
    }

    /**
     * Whether this is the correction path.
     *
     * The two routes differ by method, not by an input flag, so a submitted
     * sheet cannot be silently rewritten by replaying the plain save endpoint.
     */
    private function isCorrection(): bool
    {
        return $this->isMethod('PUT') || $this->routeIs('guru.absensi.correct');
    }
}
