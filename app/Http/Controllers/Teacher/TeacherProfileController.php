<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\GuardianRelationshipType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\UpdateTeacherProfileRequest;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Teacher;
use App\Services\AssignmentAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A teacher's own biodata.
 *
 * Three separate blocks, because they have three different owners:
 *
 *  1. account information — the User row, which the teacher sees read-only
 *     because the email is the credential they sign in with;
 *  2. teacher biodata — the officially recorded record (NIP, employment status,
 *     position), entirely read-only here, plus the two contact fields the
 *     teacher does maintain themselves;
 *  3. academic assignments — what they actually teach, derived from the same
 *     assignment records the timetable is built from.
 */
class TeacherProfileController extends Controller
{
    public function show(Request $request, AssignmentAdministrationService $assignments): View|RedirectResponse
    {
        $teacher = $request->user()->teacher;

        if (! $teacher instanceof Teacher) {
            return redirect()->route('dashboard.guru')
                ->with('status', 'Data guru belum terhubung ke akun ini.');
        }

        $activeYear = AcademicYear::getCurrent();

        $teachingAssignments = $teacher->teachingAssignments()
            ->with(['class:id,grade_level,name,academic_year_id', 'subject:id,code,name', 'term:id,label,sequence,status'])
            ->orderByDesc('academic_year_id')
            ->orderBy('class_id')
            ->orderBy('subject_id')
            ->get();

        $homeroomAssignments = $teacher->classTeacherAssignments()
            ->with(['class:id,grade_level,name,academic_year_id'])
            ->orderByDesc('id')
            ->get();

        $classIds = $teachingAssignments->pluck('class_id')
            ->merge($homeroomAssignments->pluck('class_id'))
            ->unique()
            ->values();

        $classes = $classIds->isEmpty()
            ? collect()
            : ClassModel::query()
                ->whereIn('id', $classIds)
                ->orderBy('grade_level')
                ->orderBy('name')
                ->get(['id', 'grade_level', 'name']);

        return view('guru.profil', [
            'user' => $request->user(),
            'teacher' => $teacher,
            'activeAcademicYear' => $activeYear,
            'teachingAssignments' => $teachingAssignments,
            'homeroomAssignments' => $homeroomAssignments,
            'classes' => $classes,
            'subjects' => $teachingAssignments->pluck('subject')->filter()->unique('id')->values(),
            'relationships' => GuardianRelationshipType::cases(),
        ]);
    }

    public function update(UpdateTeacherProfileRequest $request): RedirectResponse
    {
        $teacher = $request->user()->teacher;
        $validated = $request->validated();

        $teacher->fill([
            'phone' => $this->blankToNull($validated['phone'] ?? null),
            'address' => $this->blankToNull($validated['address'] ?? null),
        ])->save();

        return redirect()
            ->route('guru.profil')
            ->with('status', 'Data kontak guru tersimpan.');
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
