<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The teacher's own workspace.
 *
 * A teacher may not open the administrator master data screens, so the classes
 * they are assigned to are exposed here instead. Every read is scoped through
 * ClassPolicy::view, which resolves the scope from the teacher's own homeroom
 * and teaching assignments, so this controller cannot widen it.
 */
class TeacherWorkspaceController extends Controller
{
    public function classes(Request $request, AuditLogService $audit): View
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('viewAny', ClassModel::class);

        $teacher = $user->teacher;

        if (! $teacher instanceof Teacher || ! $teacher->isActive()) {
            return view('guru.classes.index', ['classes' => new Collection, 'hasProfile' => false]);
        }

        $classIds = $this->assignedClassIds($teacher);

        $classes = $classIds->isEmpty()
            ? new Collection
            : ClassModel::query()
                ->with('academicYear:id,code,label')
                ->withCount(['enrollments' => fn ($query) => $query->where('status', EnrollmentStatus::Active->value)])
                ->whereIn('id', $classIds)
                ->orderBy('grade_level')
                ->orderBy('name')
                ->get();

        $audit->recordOrFail(
            $user,
            'teacher.class_list.viewed',
            'teacher',
            $teacher->getKey(),
            context: ['class_count' => $classes->count()],
            request: $request,
        );

        return view('guru.classes.index', [
            'classes' => $classes,
            'hasProfile' => true,
            'homeroomClassIds' => $teacher->activeClassTeacherAssignments()->pluck('class_id'),
        ]);
    }

    public function show(Request $request, ClassModel $class, AuditLogService $audit): View
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('viewRoster', $class);

        $students = Enrollment::query()
            ->with('student:id,legal_name,preferred_name,student_number,status')
            ->where('class_id', $class->getKey())
            ->where('status', EnrollmentStatus::Active->value)
            ->orderBy('enrolled_on')
            ->get();

        $audit->recordOrFail(
            $user,
            'teacher.class_roster.viewed',
            'class',
            $class->getKey(),
            context: ['student_count' => $students->count()],
            request: $request,
        );

        return view('guru.classes.show', [
            'class' => $class->load('academicYear:id,code,label'),
            'students' => $students,
            'isHomeroom' => $user->teacher?->activeClassTeacherAssignments()->where('class_id', $class->getKey())->exists() ?? false,
        ]);
    }

    /**
     * @return Collection<int, int>
     */
    private function assignedClassIds(Teacher $teacher): Collection
    {
        return $teacher->activeClassTeacherAssignments()->pluck('class_id')
            ->merge(
                DB::table('teaching_assignments')
                    ->join('classes', 'classes.id', '=', 'teaching_assignments.class_id')
                    ->where('teaching_assignments.teacher_id', $teacher->getKey())
                    ->where('teaching_assignments.status', AssignmentStatus::Active->value)
                    ->whereDate('teaching_assignments.starts_on', '<=', today())
                    ->where(function ($query): void {
                        $query->whereNull('teaching_assignments.ends_on')
                            ->orWhereDate('teaching_assignments.ends_on', '>=', today());
                    })
                    ->pluck('teaching_assignments.class_id'),
            )
            ->unique()
            ->values();
    }
}
