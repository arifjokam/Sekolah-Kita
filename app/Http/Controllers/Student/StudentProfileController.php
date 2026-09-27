<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\UpdateStudentProfileRequest;
use App\Models\AcademicYear;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A student's own biodata.
 *
 * The same split as the teacher's screen: officially recorded identity is
 * read-only, the two contact fields the student maintains are editable, and the
 * account block is informational. The student is always resolved from the
 * authenticated user, so there is no student id in the URL to swap.
 */
class StudentProfileController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $student = $request->user()->student;

        if (! $student instanceof Student) {
            return redirect()->route('dashboard.siswa')
                ->with('status', 'Data siswa belum terhubung ke akun ini.');
        }

        $enrollment = $student->currentEnrollment();
        $history = $student->enrollments()
            ->with(['class:id,grade_level,name', 'academicYear:id,code,label'])
            ->orderByDesc('enrolled_on')
            ->limit(5)
            ->get();

        return view('siswa.profil', [
            'user' => $request->user(),
            'student' => $student,
            'enrollment' => $enrollment,
            'class' => $enrollment?->class,
            'activeAcademicYear' => AcademicYear::getCurrent(),
            'guardians' => $student->guardians()
                ->whereNull('student_guardians.ends_on')
                ->orderByDesc('is_primary_contact')
                ->orderBy('priority_order')
                ->get(),
            'history' => $history,
        ]);
    }

    public function update(UpdateStudentProfileRequest $request): RedirectResponse
    {
        $student = $request->user()->student;
        $validated = $request->validated();

        $student->fill([
            'phone' => $this->blankToNull($validated['phone'] ?? null),
            'address' => $this->blankToNull($validated['address'] ?? null),
        ])->save();

        return redirect()
            ->route('siswa.profil')
            ->with('status', 'Data kontak siswa tersimpan.');
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
