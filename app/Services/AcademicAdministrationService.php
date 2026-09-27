<?php

namespace App\Services;

use App\Enums\AcademicYearStatus;
use App\Enums\ClassStatus;
use App\Enums\TermStatus;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Administrator write path for the academic master data.
 *
 * Academic years, terms, classes and subjects are the four records every other
 * academic relation hangs off, so they are written together in one place to keep
 * their cross-record rules (a single active year, a single active term, one
 * unique class name per grade) in a single reviewable spot.
 */
class AcademicAdministrationService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /*
    |--------------------------------------------------------------------------
    | Academic years
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return Builder<AcademicYear>
     */
    public function years(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        return AcademicYear::query()
            ->withCount(['terms', 'classes', 'enrollments'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $query): Builder => $query
                    ->where('code', 'like', $like)
                    ->orWhere('label', 'like', $like));
            })
            ->when($status !== '', fn (Builder $query): Builder => $query->where('status', $status))
            ->orderByDesc('starts_on')
            ->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createYear(User $actor, array $data): AcademicYear
    {
        Gate::forUser($actor)->authorize('create', AcademicYear::class);
        $this->assertYearDates($data);

        $year = DB::transaction(function () use ($actor, $data): AcademicYear {
            $year = AcademicYear::query()->create([
                'code' => (string) $data['code'],
                'label' => (string) $data['label'],
                'starts_on' => (string) $data['starts_on'],
                'ends_on' => (string) $data['ends_on'],
                'status' => AcademicYearStatus::from((string) $data['status']),
                'description' => $this->blankToNull($data['description'] ?? null),
            ]);

            $this->audit->recordOrFail($actor, 'academic_year.created', 'academic_year', $year->getKey(), context: [
                'status' => $year->status->value,
            ]);

            return $year;
        });

        return $year->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateYear(User $actor, AcademicYear $year, array $data): AcademicYear
    {
        Gate::forUser($actor)->authorize('update', $year);
        $this->assertYearDates([
            'starts_on' => $data['starts_on'] ?? $year->starts_on?->toDateString(),
            'ends_on' => $data['ends_on'] ?? $year->ends_on?->toDateString(),
        ]);

        return DB::transaction(function () use ($actor, $year, $data): AcademicYear {
            $year->fill([
                'code' => (string) $data['code'],
                'label' => (string) $data['label'],
                'starts_on' => (string) $data['starts_on'],
                'ends_on' => (string) $data['ends_on'],
                'status' => AcademicYearStatus::from((string) $data['status']),
                'description' => $this->blankToNull($data['description'] ?? null),
            ])->save();

            $this->audit->recordOrFail($actor, 'academic_year.updated', 'academic_year', $year->getKey(), context: [
                'status' => $year->status->value,
            ]);

            return $year;
        })->refresh();
    }

    /**
     * Activate, close or return a year to draft.
     *
     * Exactly one academic year may be active: the platform treats "the current
     * academic year" as a single value, and two active rows would make every
     * current-scope query ambiguous.
     */
    public function changeYearStatus(User $actor, AcademicYear $year, AcademicYearStatus $status): AcademicYear
    {
        Gate::forUser($actor)->authorize('update', $year);

        if ($year->status === $status) {
            return $year;
        }

        if ($status === AcademicYearStatus::Active) {
            AcademicYear::query()
                ->whereKeyNot($year->getKey())
                ->where('status', AcademicYearStatus::Active->value)
                ->update(['status' => AcademicYearStatus::Closed->value]);

            if ($year->terms()->where('status', TermStatus::Active->value)->count() > 1) {
                throw ValidationException::withMessages([
                    'status' => 'Tahun ajaran ini memiliki lebih dari satu term aktif. Tentukan satu term aktif terlebih dahulu.',
                ]);
            }
        }

        return DB::transaction(function () use ($actor, $year, $status): AcademicYear {
            $year->forceFill(['status' => $status])->save();

            $this->audit->recordOrFail($actor, 'academic_year.status_changed', 'academic_year', $year->getKey(), context: [
                'status' => $status->value,
            ]);

            return $year;
        })->refresh();
    }

    /*
    |--------------------------------------------------------------------------
    | Terms
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array{search?: string|null, status?: string|null, academic_year_id?: int|null}  $filters
     * @return Builder<Term>
     */
    public function terms(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $academicYearId = (int) ($filters['academic_year_id'] ?? 0);

        return Term::query()
            ->with('academicYear:id,code,label')
            ->withCount('teachingAssignments')
            ->when($academicYearId > 0, fn (Builder $query): Builder => $query->where('academic_year_id', $academicYearId))
            ->when($search !== '', fn (Builder $query): Builder => $query->where('label', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($status !== '', fn (Builder $query): Builder => $query->where('status', $status))
            ->orderByDesc('academic_year_id')
            ->orderBy('sequence');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTerm(User $actor, array $data): Term
    {
        Gate::forUser($actor)->authorize('create', Term::class);
        $this->assertTermDates($data);
        $this->assertTermDoesNotOverlap($data, null);

        $term = DB::transaction(function () use ($actor, $data): Term {
            $status = TermStatus::from((string) $data['status']);

            // Only one semester of a year may be active: "the current semester" is
            // a single value every current-scope query depends on. Activating one
            // through the create form therefore closes its sibling rather than
            // leaving two active rows behind.
            if ($status === TermStatus::Active) {
                Term::query()
                    ->where('academic_year_id', (int) $data['academic_year_id'])
                    ->where('status', TermStatus::Active->value)
                    ->update(['status' => TermStatus::Closed->value]);
            }

            $term = Term::query()->create([
                'academic_year_id' => (int) $data['academic_year_id'],
                'sequence' => (int) $data['sequence'],
                'label' => (string) $data['label'],
                'starts_on' => (string) $data['starts_on'],
                'ends_on' => (string) $data['ends_on'],
                'status' => $status,
            ]);

            $this->audit->recordOrFail($actor, 'term.created', 'term', $term->getKey(), context: [
                'status' => $term->status->value,
                'academic_year_id' => $term->academic_year_id,
                'sequence' => $term->sequence,
            ]);

            return $term;
        });

        return $term->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateTerm(User $actor, Term $term, array $data): Term
    {
        Gate::forUser($actor)->authorize('update', $term);
        $this->assertTermDates([
            'starts_on' => $data['starts_on'] ?? $term->starts_on?->toDateString(),
            'ends_on' => $data['ends_on'] ?? $term->ends_on?->toDateString(),
        ]);
        $this->assertTermDoesNotOverlap($data, $term);

        return DB::transaction(function () use ($actor, $term, $data): Term {
            $status = TermStatus::from((string) $data['status']);

            if ($status === TermStatus::Active) {
                Term::query()
                    ->where('academic_year_id', (int) $data['academic_year_id'])
                    ->whereKeyNot($term->getKey())
                    ->where('status', TermStatus::Active->value)
                    ->update(['status' => TermStatus::Closed->value]);
            }

            $term->fill([
                'academic_year_id' => (int) $data['academic_year_id'],
                'sequence' => (int) $data['sequence'],
                'label' => (string) $data['label'],
                'starts_on' => (string) $data['starts_on'],
                'ends_on' => (string) $data['ends_on'],
                'status' => $status,
            ])->save();

            $this->audit->recordOrFail($actor, 'term.updated', 'term', $term->getKey(), context: [
                'status' => $term->status->value,
                'academic_year_id' => $term->academic_year_id,
                'sequence' => $term->sequence,
            ]);

            return $term;
        })->refresh();
    }

    public function changeTermStatus(User $actor, Term $term, TermStatus $status): Term
    {
        Gate::forUser($actor)->authorize('update', $term);

        if ($term->status === $status) {
            return $term;
        }

        return DB::transaction(function () use ($actor, $term, $status): Term {
            if ($status === TermStatus::Active) {
                Term::query()
                    ->where('academic_year_id', $term->academic_year_id)
                    ->whereKeyNot($term->getKey())
                    ->where('status', TermStatus::Active->value)
                    ->update(['status' => TermStatus::Closed->value]);
            }

            $term->forceFill(['status' => $status])->save();

            $this->audit->recordOrFail($actor, 'term.status_changed', 'term', $term->getKey(), context: [
                'status' => $status->value,
            ]);

            return $term;
        })->refresh();
    }

    /*
    |--------------------------------------------------------------------------
    | Classes
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array{search?: string|null, status?: string|null, academic_year_id?: int|null}  $filters
     * @return Builder<ClassModel>
     */
    public function classes(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $academicYearId = (int) ($filters['academic_year_id'] ?? 0);

        return ClassModel::query()
            ->with('academicYear:id,code,label')
            ->withCount(['enrollments', 'classTeacherAssignments'])
            ->when($academicYearId > 0, fn (Builder $query): Builder => $query->where('academic_year_id', $academicYearId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where('name', 'like', $like);
            })
            ->when($status !== '', fn (Builder $query): Builder => $query->where('status', $status))
            ->orderByDesc('academic_year_id')
            ->orderBy('grade_level')
            ->orderBy('name');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createClass(User $actor, array $data): ClassModel
    {
        Gate::forUser($actor)->authorize('create', ClassModel::class);

        $class = DB::transaction(function () use ($actor, $data): ClassModel {
            $class = ClassModel::query()->create([
                'academic_year_id' => (int) $data['academic_year_id'],
                'grade_level' => (int) $data['grade_level'],
                'name' => (string) $data['name'],
                'capacity' => $this->blankToNull($data['capacity'] ?? null),
                'status' => ClassStatus::from((string) $data['status']),
            ]);

            $this->audit->recordOrFail($actor, 'class.created', 'class', $class->getKey(), context: [
                'status' => $class->status->value,
            ]);

            return $class;
        });

        return $class->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateClass(User $actor, ClassModel $class, array $data): ClassModel
    {
        Gate::forUser($actor)->authorize('update', $class);

        return DB::transaction(function () use ($actor, $class, $data): ClassModel {
            $capacity = $this->blankToNull($data['capacity'] ?? null);

            if ($capacity !== null && (int) $capacity < $class->enrollments()->count()) {
                throw ValidationException::withMessages([
                    'capacity' => 'Kapasitas tidak boleh lebih kecil dari jumlah enrollment yang sudah ada.',
                ]);
            }

            $class->fill([
                'academic_year_id' => (int) $data['academic_year_id'],
                'grade_level' => (int) $data['grade_level'],
                'name' => (string) $data['name'],
                'capacity' => $capacity,
                'status' => ClassStatus::from((string) $data['status']),
            ])->save();

            $this->audit->recordOrFail($actor, 'class.updated', 'class', $class->getKey(), context: [
                'status' => $class->status->value,
            ]);

            return $class;
        })->refresh();
    }

    public function changeClassStatus(User $actor, ClassModel $class, ClassStatus $status): ClassModel
    {
        Gate::forUser($actor)->authorize('update', $class);

        if ($class->status === $status) {
            return $class;
        }

        if ($status === ClassStatus::Active && $class->academicYear?->status !== AcademicYearStatus::Active) {
            throw ValidationException::withMessages([
                'status' => 'Kelas hanya dapat diaktifkan pada tahun ajaran yang aktif.',
            ]);
        }

        return DB::transaction(function () use ($actor, $class, $status): ClassModel {
            $class->forceFill([
                'status' => $status,
                'closed_at' => $status === ClassStatus::Closed ? ($class->closed_at ?? today()->toDateString()) : null,
                'closure_reason' => $status === ClassStatus::Closed ? ($class->closure_reason ?? 'Ditutup administrator') : null,
            ])->save();

            $this->audit->recordOrFail($actor, 'class.status_changed', 'class', $class->getKey(), context: [
                'status' => $status->value,
            ]);

            return $class;
        })->refresh();
    }

    /*
    |--------------------------------------------------------------------------
    | Subjects
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return Builder<Subject>
     */
    public function subjects(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        return Subject::query()
            ->withCount('teachingAssignments')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $query): Builder => $query
                    ->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like));
            })
            ->when($status !== '', fn (Builder $query): Builder => $query->where('status', $status))
            ->orderBy('code')
            ->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createSubject(User $actor, array $data): Subject
    {
        Gate::forUser($actor)->authorize('create', Subject::class);

        $subject = DB::transaction(function () use ($actor, $data): Subject {
            $subject = Subject::query()->create([
                'code' => (string) $data['code'],
                'name' => (string) $data['name'],
                'description' => $this->blankToNull($data['description'] ?? null),
                'status' => (string) $data['status'],
            ]);

            $this->audit->recordOrFail($actor, 'subject.created', 'subject', $subject->getKey(), context: [
                'status' => $subject->status,
            ]);

            return $subject;
        });

        return $subject->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSubject(User $actor, Subject $subject, array $data): Subject
    {
        Gate::forUser($actor)->authorize('update', $subject);

        return DB::transaction(function () use ($actor, $subject, $data): Subject {
            $subject->fill([
                'code' => (string) $data['code'],
                'name' => (string) $data['name'],
                'description' => $this->blankToNull($data['description'] ?? null),
                'status' => (string) $data['status'],
            ])->save();

            $this->audit->recordOrFail($actor, 'subject.updated', 'subject', $subject->getKey(), context: [
                'status' => $subject->status,
            ]);

            return $subject;
        })->refresh();
    }

    public function changeSubjectStatus(User $actor, Subject $subject, string $status): Subject
    {
        Gate::forUser($actor)->authorize('update', $subject);

        if ($subject->status === $status) {
            return $subject;
        }

        return DB::transaction(function () use ($actor, $subject, $status): Subject {
            $subject->forceFill(['status' => $status])->save();

            $this->audit->recordOrFail($actor, 'subject.status_changed', 'subject', $subject->getKey(), context: [
                'status' => $status,
            ]);

            return $subject;
        })->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertYearDates(array $data): void
    {
        $startsOn = (string) ($data['starts_on'] ?? '');
        $endsOn = (string) ($data['ends_on'] ?? '');

        if ($startsOn === '' || $endsOn === '' || strtotime($startsOn) >= strtotime($endsOn)) {
            throw ValidationException::withMessages([
                'ends_on' => 'Tanggal selesai harus setelah tanggal mulai.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertTermDates(array $data): void
    {
        $startsOn = (string) ($data['starts_on'] ?? '');
        $endsOn = (string) ($data['ends_on'] ?? '');

        if ($startsOn === '' || $endsOn === '' || strtotime($startsOn) >= strtotime($endsOn)) {
            throw ValidationException::withMessages([
                'ends_on' => 'Tanggal selesai harus setelah tanggal mulai.',
            ]);
        }
    }

    /**
     * Two semesters of one academic year may not occupy the same dates.
     *
     * The form request already reports this per field; repeating it here means a
     * write that arrives by any other path still cannot produce overlapping
     * semesters.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertTermDoesNotOverlap(array $data, ?Term $term = null): void
    {
        $yearId = (int) ($data['academic_year_id'] ?? 0);
        $startsOn = (string) ($data['starts_on'] ?? '');
        $endsOn = (string) ($data['ends_on'] ?? '');

        if ($yearId <= 0 || $startsOn === '' || $endsOn === '') {
            return;
        }

        $overlaps = Term::query()
            ->where('academic_year_id', $yearId)
            ->when($term instanceof Term, fn (Builder $query): Builder => $query->whereKeyNot($term->getKey()))
            ->where('starts_on', '<=', $endsOn)
            ->where('ends_on', '>=', $startsOn)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'starts_on' => 'Periode semester ini bertumpuk dengan semester lain pada tahun ajaran yang sama.',
            ]);
        }
    }

    private function blankToNull(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : (is_numeric($string) ? (int) $string : $string);
    }
}
