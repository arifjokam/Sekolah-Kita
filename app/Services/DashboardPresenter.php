<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\AdmissionApplicationStatus;
use App\Enums\AdmissionConversionStatus;
use App\Enums\AdmissionPeriodStatus;
use App\Enums\ClassStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\PublicationStatus;
use App\Enums\StudentLifecycleStatus;
use App\Enums\TeacherEmploymentStatus;
use App\Enums\TermStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AdmissionApplication;
use App\Models\AdmissionPeriod;
use App\Models\AuditLog;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\NewsArticle;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read model behind the three role dashboards.
 *
 * Every figure is a real aggregate query and every teacher or student figure is
 * resolved through the owning user record, so a dashboard can never widen the
 * visibility its policies already restrict. Nothing here writes, so the
 * dashboards never generate audit traffic of their own.
 */
class DashboardPresenter
{
    public function __construct(
        private readonly HealthCheckService $health,
        private readonly ApplicantPortalService $portal,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forAdministrator(User $user): array
    {
        return [
            'activeAcademicYear' => AcademicYear::getCurrent(),
            'academicYearCount' => AcademicYear::query()->count(),
            'activeTermCount' => Term::query()->where('status', TermStatus::Active->value)->count(),
            'classCount' => ClassModel::query()->count(),
            'activeClassCount' => ClassModel::query()->where('status', ClassStatus::Active->value)->count(),
            'subjectCount' => Subject::query()->count(),
            'teacherCount' => Teacher::query()->count(),
            'activeTeacherCount' => Teacher::query()->where('employment_status', TeacherEmploymentStatus::Active->value)->count(),
            'studentCount' => Student::query()->count(),
            'activeStudentCount' => Student::query()->where('status', StudentLifecycleStatus::Active->value)->count(),
            'enrollmentCount' => Enrollment::query()->where('status', EnrollmentStatus::Active->value)->count(),
            'publishedNewsCount' => NewsArticle::query()->where('status', PublicationStatus::Published->value)->count(),
            'draftNewsCount' => NewsArticle::query()->where('status', PublicationStatus::Draft->value)->count(),
            'activePeriod' => $this->activePeriod(),
            'openPeriodCount' => AdmissionPeriod::query()->where('status', AdmissionPeriodStatus::Open->value)->count(),
            'applicationCount' => AdmissionApplication::query()->count(),
            'awaitingReviewCount' => AdmissionApplication::query()
                ->whereIn('status', [
                    AdmissionApplicationStatus::Submitted->value,
                    AdmissionApplicationStatus::UnderReview->value,
                ])
                ->count(),
            'pendingConversionCount' => AdmissionApplication::query()
                ->where('status', AdmissionApplicationStatus::Accepted->value)
                ->where('conversion_status', '!=', AdmissionConversionStatus::Completed->value)
                ->count(),
            'duplicateFlagCount' => AdmissionApplication::query()->where('duplicate_flag', true)->count(),
            'administratorCount' => User::query()
                ->where('role', UserRole::Administrator->value)
                ->where('status', AccountStatus::Active->value)
                ->count(),
            'recentApplications' => AdmissionApplication::query()
                ->with('period:id,name')
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->limit(6)
                ->get(['id', 'admission_period_id', 'registration_number', 'status', 'duplicate_flag', 'submitted_at']),
            'recentAuditEvents' => AuditLog::query()
                ->with('actor:id,name')
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(8)
                ->get(['id', 'actor_user_id', 'action', 'result', 'target_type', 'target_id', 'occurred_at']),
            'recentNotifications' => $user->notifications()->orderByDesc('created_at')->limit(6)->get(),
            'unreadNotificationCount' => $user->unreadNotifications()->count(),
            'health' => $this->health->check(),
            'quickActions' => [
                ['label' => 'Tambah guru', 'route' => 'admin.teachers.create'],
                ['label' => 'Tambah siswa', 'route' => 'admin.students.create'],
                ['label' => 'Tambah kelas', 'route' => 'admin.classes.create'],
                ['label' => 'Tambah mata pelajaran', 'route' => 'admin.subjects.create'],
                ['label' => 'Buat berita', 'route' => 'admin.news.create'],
                ['label' => 'Kelola PPDB', 'route' => 'admin.ppdb.periods.index'],
                ['label' => 'Lihat pengajuan', 'route' => 'admin.ppdb.applications.index'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forTeacher(User $user): array
    {
        $teacher = $user->teacher;
        $activeYear = AcademicYear::getCurrent();
        $unread = $user->unreadNotifications()->count();
        $recent = $user->notifications()->orderByDesc('created_at')->limit(6)->get();

        if (! $teacher instanceof Teacher) {
            return [
                'teacher' => null,
                'hasProfile' => false,
                'activeAcademicYear' => $activeYear,
                'assignedClasses' => new Collection,
                'assignedSubjects' => new Collection,
                'homeroomClasses' => new Collection,
                'teachingAssignments' => new Collection,
                'scopedStudentCount' => 0,
                'recentNotifications' => $recent,
                'unreadNotificationCount' => $unread,
                'quickActions' => [
                    ['label' => 'Kelas yang saya ampu', 'route' => 'guru.classes.index'],
                    ['label' => 'Notifikasi', 'route' => 'notifications.index'],
                ],
            ];
        }

        $homeroomClasses = $teacher->activeClassTeacherAssignments()
            ->with('class.academicYear:id,code,label')
            ->get();

        $teachingAssignments = $teacher->activeTeachingAssignments()
            ->with([
                'class.academicYear:id,code,label',
                'subject:id,code,name',
                'term:id,label,sequence',
            ])
            ->orderBy('class_id')
            ->orderBy('subject_id')
            ->get();

        $classIds = $teachingAssignments->pluck('class_id')
            ->merge($homeroomClasses->pluck('class_id'))
            ->unique()
            ->values();

        $assignedClasses = $classIds->isEmpty()
            ? new Collection
            : ClassModel::query()
                ->with('academicYear:id,code,label')
                ->whereIn('id', $classIds)
                ->orderBy('grade_level')
                ->orderBy('name')
                ->get();

        $scopedStudentCount = $classIds->isEmpty()
            ? 0
            : Student::query()
                ->whereIn('id', Enrollment::query()
                    ->whereIn('class_id', $classIds)
                    ->where('status', EnrollmentStatus::Active->value)
                    ->select('student_id'))
                ->count();

        return [
            'teacher' => $teacher,
            'hasProfile' => true,
            'activeAcademicYear' => $activeYear,
            'assignedClasses' => $assignedClasses,
            'assignedSubjects' => $teachingAssignments->pluck('subject')->unique('id')->values(),
            'homeroomClasses' => $homeroomClasses,
            'teachingAssignments' => $teachingAssignments,
            'scopedStudentCount' => $scopedStudentCount,
            'recentNotifications' => $recent,
            'unreadNotificationCount' => $unread,
            // A teacher is not an administrator, so these links are limited to
            // the routes the role middleware actually lets them open.
            'quickActions' => [
                ['label' => 'Kelas yang saya ampu', 'route' => 'guru.classes.index'],
                ['label' => 'Notifikasi', 'route' => 'notifications.index'],
                ['label' => 'Berita sekolah', 'route' => 'news.index'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forStudent(User $user): array
    {
        $student = $user->student;
        $activeYear = AcademicYear::getCurrent();

        $guardians = $student instanceof Student
            ? $student->guardians()
                ->whereNull('student_guardians.ends_on')
                ->orderByDesc('is_primary_contact')
                ->orderBy('priority_order')
                ->get()
            : new Collection;

        return [
            'student' => $student,
            'hasProfile' => $student instanceof Student,
            'activeAcademicYear' => $activeYear,
            'enrollment' => $student?->currentEnrollment(),
            'class' => $student?->currentClass(),
            'enrollmentHistory' => $student instanceof Student
                ? $student->enrollments()
                    ->with(['class:id,grade_level,name', 'academicYear:id,code,label'])
                    ->orderByDesc('enrolled_on')
                    ->limit(5)
                    ->get()
                : new Collection,
            'guardians' => $guardians,
            'announcements' => NewsArticle::query()
                ->where('status', PublicationStatus::Published->value)
                ->where('published_at', '<=', now())
                ->orderByDesc('published_at')
                ->limit(5)
                ->get(['id', 'title', 'slug', 'excerpt', 'published_at']),
            'openPeriods' => AdmissionPeriod::query()
                ->where('status', AdmissionPeriodStatus::Open->value)
                ->where('opens_at', '<=', now())
                ->where('closes_at', '>=', now())
                ->orderBy('closes_at')
                ->limit(3)
                ->get(['id', 'name', 'slug', 'opens_at', 'closes_at']),
            'recentNotifications' => $user->notifications()->orderByDesc('created_at')->limit(6)->get(),
            'unreadNotificationCount' => $user->unreadNotifications()->count(),
            'quickActions' => [
                ['label' => 'Berita sekolah', 'route' => 'news.index'],
                ['label' => 'Prestasi sekolah', 'route' => 'achievements.index'],
                ['label' => 'Galeri kegiatan', 'route' => 'gallery.index'],
                ['label' => 'PPDB', 'route' => 'ppdb.index'],
            ],
        ];
    }

    /**
     * The PPDB applicant landing page.
     *
     * Delegates to ApplicantPortalService::progress() so the dashboard and the
     * detailed portal screens can never disagree about what is still missing.
     * A candidate with no application at all gets an explanatory page rather than
     * a blank one.
     *
     * @return array<string, mixed>
     */
    public function forApplicant(User $user): array
    {
        $application = $this->portal->currentApplication($user, true);

        if (! $application instanceof AdmissionApplication) {
            return [
                'application' => null,
                'hasApplication' => false,
                'openPeriod' => $this->activePeriod(),
                'recentNotifications' => $user->notifications()->orderByDesc('created_at')->limit(6)->get(),
                'unreadNotificationCount' => $user->unreadNotifications()->count(),
            ];
        }

        $progress = $this->portal->progress($user, $application);

        return array_merge($progress, [
            'hasApplication' => true,
            'recentNotifications' => $user->notifications()->orderByDesc('created_at')->limit(6)->get(),
            'unreadNotificationCount' => $user->unreadNotifications()->count(),
        ]);
    }

    private function activePeriod(): ?AdmissionPeriod
    {
        return AdmissionPeriod::query()
            ->whereIn('status', [AdmissionPeriodStatus::Open->value, AdmissionPeriodStatus::Draft->value])
            ->orderByRaw("case when status = 'open' then 0 else 1 end")
            ->orderByDesc('opens_at')
            ->first();
    }

    /**
     * Liveness and readiness are answered by HealthCheckService; the dashboard
     * only needs the aggregate, so it is requested once and shared.
     */
    public function cacheProbe(): bool
    {
        try {
            return Cache::put('dashboard:probe', 1, 10);
        } catch (Throwable) {
            return false;
        }
    }

    public function databaseProbe(): bool
    {
        try {
            return DB::selectOne('SELECT 1 AS healthy') !== null;
        } catch (Throwable) {
            return false;
        }
    }
}
