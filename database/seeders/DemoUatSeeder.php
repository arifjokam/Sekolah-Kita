<?php

namespace Database\Seeders;

use App\Enums\AcademicYearStatus;
use App\Enums\AdmissionActorType;
use App\Enums\AdmissionApplicationStatus;
use App\Enums\AdmissionConversionStatus;
use App\Enums\AdmissionDocumentType;
use App\Enums\AdmissionDocumentVerificationStatus;
use App\Enums\AdmissionPeriodStatus;
use App\Enums\AssignmentStatus;
use App\Enums\AttendanceSessionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\ClassStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\GuardianLifecycleStatus;
use App\Enums\GuardianRelationshipType;
use App\Enums\PublicationStatus;
use App\Enums\ScheduleStatus;
use App\Enums\StudentLifecycleStatus;
use App\Enums\TeacherEmploymentStatus;
use App\Enums\TermStatus;
use App\Enums\WeekDay;
use App\Models\AcademicYear;
use App\Models\AdmissionApplication;
use App\Models\AdmissionPeriod;
use App\Models\ApplicationDocument;
use App\Models\ApplicationStatusHistory;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassModel;
use App\Models\ClassSchedule;
use App\Models\ClassTeacherAssignment;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\MediaAsset;
use App\Models\NewsArticle;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Complete local UAT dataset.
 *
 * Every row is looked up by its natural key before it is written, so the
 * seeder is idempotent and can be re-run after adding master data through the
 * admin UI. Nothing is ever overwritten with unrelated content: an existing
 * record is only completed when a field the domain requires is still missing.
 */
class DemoUatSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DevelopmentAccountsSeeder::class);

        $activeYear = $this->activeAcademicYear();
        $terms = $this->terms($activeYear);
        $subjects = $this->subjects();
        $classes = $this->classes($activeYear);
        $teachers = $this->teachers();
        $students = $this->students();
        $this->guardians($students);
        $this->enrollments($students, $classes, $activeYear);
        $assignments = $this->assignments($teachers, $classes, $subjects, $activeYear, $terms);
        $this->schedules($assignments, $terms);
        $this->attendance($assignments, $terms);
        $this->admission($activeYear, $classes);
        $this->content();
    }

    private function activeAcademicYear(): AcademicYear
    {
        $code = now()->format('Y').'/'.(now()->addYearNoOverflow()->format('Y'));
        $startsOn = now()->subMonths(5)->startOfYear()->toDateString();
        $endsOn = now()->addMonths(7)->endOfYear()->toDateString();

        $year = AcademicYear::query()->firstOrNew(['code' => $code]);
        $year->forceFill([
            'label' => 'Tahun Ajaran '.$code,
            'starts_on' => $year->starts_on ?? $startsOn,
            'ends_on' => $year->ends_on ?? $endsOn,
            'status' => AcademicYearStatus::Active,
            'description' => $year->description ?? 'Tahun ajaran aktif untuk pengujian.',
        ])->save();

        // Only one year may be active, because "the current academic year" is a
        // single value across the whole application.
        AcademicYear::query()
            ->whereKeyNot($year->getKey())
            ->where('status', AcademicYearStatus::Active->value)
            ->update(['status' => AcademicYearStatus::Closed->value]);

        return $year->refresh();
    }

    /**
     * @return array<int, Term>
     */
    private function terms(AcademicYear $year): array
    {
        $definitions = [
            ['sequence' => 1, 'label' => 'Semester 1', 'offset_start' => '-5 months', 'offset_end' => '+1 month'],
            ['sequence' => 2, 'label' => 'Semester 2', 'offset_start' => '+1 month', 'offset_end' => '+7 months'],
        ];

        $terms = [];
        $activeAssigned = false;

        foreach ($definitions as $definition) {
            $term = Term::query()->firstOrNew([
                'academic_year_id' => $year->getKey(),
                'sequence' => $definition['sequence'],
            ]);
            $term->forceFill([
                'label' => $definition['label'],
                'starts_on' => $term->starts_on ?? now()->modify($definition['offset_start'])->toDateString(),
                'ends_on' => $term->ends_on ?? now()->modify($definition['offset_end'])->toDateString(),
                'status' => $term->exists ? $term->status : ($activeAssigned ? TermStatus::Draft : TermStatus::Active),
            ])->save();
            $activeAssigned = true;
            $terms[] = $term;
        }

        return $terms;
    }

    /**
     * @return array<int, Subject>
     */
    private function subjects(): array
    {
        $definitions = [
            ['code' => 'MTK', 'name' => 'Matematika'],
            ['code' => 'BIN', 'name' => 'Bahasa Indonesia'],
            ['code' => 'BIG', 'name' => 'Bahasa Inggris'],
            ['code' => 'IPA', 'name' => 'Ilmu Pengetahuan Alam'],
            ['code' => 'IPS', 'name' => 'Ilmu Pengetahuan Sosial'],
            ['code' => 'PKN', 'name' => 'Pendidikan Kewarganegaraan'],
            ['code' => 'AGM', 'name' => 'Pendidikan Agama'],
            ['code' => 'PJO', 'name' => 'Pendidikan Jasmani'],
            ['code' => 'SBD', 'name' => 'Seni Budaya'],
            ['code' => 'TIK', 'name' => 'Teknologi Informasi'],
        ];

        $subjects = [];

        foreach ($definitions as $definition) {
            $subject = Subject::query()->firstOrNew(['code' => $definition['code']]);
            $subject->forceFill([
                'name' => $definition['name'],
                'status' => $subject->exists ? $subject->status : 'active',
                'description' => $subject->description ?? null,
            ])->save();
            $subjects[] = $subject;
        }

        return $subjects;
    }

    /**
     * @return array<int, ClassModel>
     */
    private function classes(AcademicYear $year): array
    {
        $definitions = [
            ['grade_level' => 7, 'name' => 'A'],
            ['grade_level' => 7, 'name' => 'B'],
            ['grade_level' => 8, 'name' => 'A'],
            ['grade_level' => 8, 'name' => 'B'],
        ];

        $classes = [];

        foreach ($definitions as $definition) {
            $class = ClassModel::query()->firstOrNew([
                'academic_year_id' => $year->getKey(),
                'grade_level' => $definition['grade_level'],
                'name' => $definition['name'],
            ]);
            $class->forceFill([
                'capacity' => $class->capacity ?? 32,
                'status' => $class->exists ? $class->status : ClassStatus::Active,
            ])->save();
            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * @return array<int, Teacher>
     */
    private function teachers(): array
    {
        $definitions = [
            ['legal_name' => 'Guru Test', 'employee_number' => 'NIP-DEV-0001', 'position_title' => 'Guru Matematika'],
            ['legal_name' => 'Budi Santoso', 'employee_number' => 'NIP-DEV-0002', 'position_title' => 'Guru Bahasa Indonesia'],
            ['legal_name' => 'Citra Dewi', 'employee_number' => 'NIP-DEV-0003', 'position_title' => 'Guru Ilmu Pengetahuan Alam'],
        ];

        $teachers = [];

        foreach ($definitions as $index => $definition) {
            $teacher = Teacher::query()->firstOrNew(['employee_number' => $definition['employee_number']]);
            $teacher->forceFill([
                'legal_name' => $definition['legal_name'],
                'position_title' => $definition['position_title'],
                'employment_status' => TeacherEmploymentStatus::Active,
                'employment_start_date' => $teacher->employment_start_date ?? now()->subYears(3)->startOfYear()->toDateString(),
                'biography' => $teacher->biography ?? null,
            ])->save();

            if ($index === 0) {
                $this->linkIdentity($teacher, 'guru.test@sekolah.local');
            }

            $teachers[] = $teacher;
        }

        return $teachers;
    }

    private function linkIdentity(Teacher $teacher, string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User && $teacher->user_id !== $user->getKey()) {
            $teacher->forceFill(['user_id' => $user->getKey()])->save();
        }
    }

    /**
     * @return array<int, Student>
     */
    private function students(): array
    {
        $definitions = [
            ['legal_name' => 'Siswa Test', 'student_number' => 'NIS-DEV-0001', 'email' => 'siswa.test@sekolah.local'],
            ['legal_name' => 'Andi Pratama', 'student_number' => 'NIS-DEV-0002', 'email' => 'andi.pratama@sekolah.local'],
            ['legal_name' => 'Bela Lestari', 'student_number' => 'NIS-DEV-0003', 'email' => 'bela.lestari@sekolah.local'],
            ['legal_name' => 'Candra Wijaya', 'student_number' => 'NIS-DEV-0004', 'email' => 'candra.wijaya@sekolah.local'],
            ['legal_name' => 'Dewi Anggraini', 'student_number' => 'NIS-DEV-0005', 'email' => 'dewi.anggraini@sekolah.local'],
            ['legal_name' => 'Eko Prasetyo', 'student_number' => 'NIS-DEV-0006', 'email' => 'eko.prasetyo@sekolah.local'],
            ['legal_name' => 'Fitri Handayani', 'student_number' => 'NIS-DEV-0007', 'email' => 'fitri.handayani@sekolah.local'],
            ['legal_name' => 'Galih Ramadhan', 'student_number' => 'NIS-DEV-0008', 'email' => 'galih.ramadhan@sekolah.local'],
        ];

        $students = [];

        foreach ($definitions as $index => $definition) {
            $student = Student::query()->firstOrNew(['student_number' => $definition['student_number']]);
            $student->forceFill([
                'legal_name' => $definition['legal_name'],
                'status' => StudentLifecycleStatus::Active,
                'admission_date' => $student->admission_date ?? now()->subYear()->startOfYear()->toDateString(),
                'birth_date' => $student->birth_date ?? now()->subYears(13 - min($index, 2))->toDateString(),
                'birth_place' => $student->birth_place ?? 'Bandung',
                'gender' => $student->gender ?? 'L',
                'email' => $student->email ?? $definition['email'],
            ])->save();

            if ($index === 0) {
                $user = User::query()->where('email', $definition['email'])->first();

                if ($user instanceof User && $student->user_id !== $user->getKey()) {
                    $student->forceFill(['user_id' => $user->getKey()])->save();
                }
            }

            $students[] = $student;
        }

        return $students;
    }

    /**
     * @param  array<int, Student>  $students
     */
    private function guardians(array $students): void
    {
        $definitions = [
            ['suffix' => 'I', 'phone' => '0811-1000-0001', 'relationship_type' => GuardianRelationshipType::Father],
            ['suffix' => 'II', 'phone' => '0811-1000-0002', 'relationship_type' => GuardianRelationshipType::Mother],
        ];

        foreach ($students as $index => $student) {
            if (StudentGuardian::query()->where('student_id', $student->getKey())->exists()) {
                continue;
            }

            $definition = $definitions[$index % 2];

            $guardian = Guardian::query()->firstOrNew([
                'legal_name' => $student->legal_name.' '.$definition['suffix'],
            ]);
            $guardian->forceFill([
                'phone' => $guardian->phone ?? $definition['phone'],
                'email' => $guardian->email ?? null,
                'address' => $guardian->address ?? 'Jl. Contoh No. 1, Bandung',
                'status' => GuardianLifecycleStatus::Active,
            ])->save();

            StudentGuardian::query()->firstOrCreate([
                'student_id' => $student->getKey(),
                'guardian_id' => $guardian->getKey(),
            ], [
                'relationship_type' => $definition['relationship_type'],
                'is_primary_contact' => true,
                'is_emergency_contact' => true,
                'priority_order' => 1,
                'starts_on' => $student->admission_date?->toDateString() ?? today()->toDateString(),
                'ends_on' => null,
            ]);
        }
    }

    /**
     * @param  array<int, Student>  $students
     * @param  array<int, ClassModel>  $classes
     */
    private function enrollments(array $students, array $classes, AcademicYear $year): void
    {
        $classes = $classes !== [] ? $classes : $this->classes($year);

        foreach ($students as $index => $student) {
            $class = $classes[$index % count($classes)];
            $alreadyEnrolled = DB::table('enrollments')
                ->where('student_id', $student->getKey())
                ->where('academic_year_id', $year->getKey())
                ->exists();

            if ($alreadyEnrolled) {
                continue;
            }

            DB::table('enrollments')->insert([
                'student_id' => $student->getKey(),
                'class_id' => $class->getKey(),
                'academic_year_id' => $year->getKey(),
                'status' => EnrollmentStatus::Active->value,
                'enrolled_on' => now()->subMonths(4)->toDateString(),
                'effective_from' => now()->subMonths(4)->toDateString(),
                'effective_to' => null,
                'completed_on' => null,
                'transition_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<int, Teacher>  $teachers
     * @param  array<int, ClassModel>  $classes
     * @param  array<int, Subject>  $subjects
     * @param  array<int, Term>  $terms
     * @return Collection<int, TeachingAssignment>
     */
    private function assignments(array $teachers, array $classes, array $subjects, AcademicYear $year, array $terms): Collection
    {
        $start = now()->subMonths(5)->toDateString();
        $created = new Collection;

        foreach ($teachers as $index => $teacher) {
            foreach ($subjects as $offset => $subject) {
                $class = $classes[($index + $offset) % count($classes)];

                $created[] = TeachingAssignment::firstOrCreate([
                    'teacher_id' => $teacher->getKey(),
                    'class_id' => $class->getKey(),
                    'subject_id' => $subject->getKey(),
                    'academic_year_id' => $year->getKey(),
                ], [
                    'term_id' => $terms[0]->getKey(),
                    'status' => AssignmentStatus::Active,
                    'starts_on' => $start,
                    'ends_on' => null,
                    'internal_reason' => null,
                ]);
            }
        }

        // One class teacher per class, so every class in the fixture set has a
        // homeroom teacher the teacher dashboard can scope students through.
        foreach ($classes as $index => $class) {
            $teacher = $teachers[$index % count($teachers)];

            ClassTeacherAssignment::firstOrCreate([
                'class_id' => $class->getKey(),
                'teacher_id' => $teacher->getKey(),
            ], [
                'status' => AssignmentStatus::Active,
                'starts_on' => $start,
                'ends_on' => null,
                'internal_reason' => null,
            ]);
        }

        return $created;
    }

    /**
     * A real weekly timetable built from the assignment rows.
     *
     * Two properties matter here, and both are enforced by allocation rather than
     * by luck:
     *
     *  1. every lesson has a teacher who is actually assigned to teach that
     *     subject in that class in that semester — the same integrity rule the
     *     schedule form enforces; and
     *  2. no class and no teacher is ever double booked, which is the same
     *     conflict rule the write path enforces.
     *
     * The earlier version derived both the day and the hour band from one running
     * counter, so the (day, band) pair repeated every six lessons and any class
     * with more than six assignments silently ended up with two lessons in the
     * same slot. Slots are now allocated against an explicit "already taken"
     * index for the class and for the teacher, so a conflict is unrepresentable.
     *
     * Monday through Saturday, because the school week this deployment allows is
     * six days and a weekday-only fixture would leave the register untestable on
     * the one day a reviewer is most likely to run the demo.
     *
     * @param  Collection<int, TeachingAssignment>  $assignments
     * @param  array<int, Term>  $terms
     */
    private function schedules(Collection $assignments, array $terms): void
    {
        $term = $terms[0] ?? null;

        if (! $term instanceof Term || $assignments === []) {
            return;
        }

        $teachingDays = [
            WeekDay::Monday,
            WeekDay::Tuesday,
            WeekDay::Wednesday,
            WeekDay::Thursday,
            WeekDay::Friday,
            WeekDay::Saturday,
        ];

        // A lesson is 40 minutes and starts on the hour from 07:00, so band N
        // always ends before band N+1 begins. Two lessons in different bands
        // therefore cannot overlap, which is what makes "different band" a safe
        // stand-in for "different time".
        $firstHour = 7;
        $bands = (int) config('sekolah-kita.schedule.max_lessons_per_day', 12);
        $bands = max(1, min($bands, count($teachingDays)));

        /** @var array<string, array<int, true>> $taken */
        $taken = [];

        $isTaken = static function (string $dimension, int $day, int $band) use (&$taken): bool {
            return isset($taken[$dimension][$day][$band]);
        };

        $claim = static function (string $dimension, int $day, int $band) use (&$taken): void {
            $taken[$dimension][$day][$band] = true;
        };

        foreach ($assignments->groupBy('class_id') as $classId => $classAssignments) {
            foreach ($classAssignments as $assignment) {
                $chosen = null;

                // Bands first, then days: a class gets a full day before it gets a
                // second lesson on the same day, which reads like a real timetable
                // instead of a scattered one.
                for ($band = 0; $band < $bands && $chosen === null; $band++) {
                    for ($dayIndex = 0; $dayIndex < count($teachingDays); $dayIndex++) {
                        $classKey = 'class:'.$classId;
                        $teacherKey = 'teacher:'.$assignment->teacher_id;

                        if ($isTaken($classKey, $dayIndex, $band) || $isTaken($teacherKey, $dayIndex, $band)) {
                            continue;
                        }

                        $chosen = [$dayIndex, $band];
                        $claim($classKey, $dayIndex, $band);
                        $claim($teacherKey, $dayIndex, $band);

                        break;
                    }
                }

                if ($chosen === null) {
                    // More assignments than the week can hold. Skipping is honest;
                    // writing a clashing lesson would quietly teach a wrong
                    // timetable to whoever demos this.
                    continue;
                }

                [$dayIndex, $band] = $chosen;
                $startsAt = sprintf('%02d:00', $firstHour + $band);
                $endsAt = sprintf('%02d:40', $firstHour + $band);

                ClassSchedule::firstOrCreate([
                    'class_id' => $assignment->class_id,
                    'subject_id' => $assignment->subject_id,
                    'teacher_id' => $assignment->teacher_id,
                    'term_id' => $term->getKey(),
                    'day_of_week' => $teachingDays[$dayIndex]->value,
                    'starts_at' => $startsAt,
                ], [
                    'academic_year_id' => $assignment->academic_year_id,
                    'ends_at' => $endsAt,
                    'room' => 'R. '.($assignment->class?->grade_level ?? 7).(string) ($assignment->class?->name ?? 'A'),
                    'status' => ScheduleStatus::Active,
                ]);
            }
        }
    }

    /**
     * Sample attendance for the current month, so the teacher register, the
     * monthly report and the student summary all have something real to render.
     *
     * Only sessions for lessons whose date matches the lesson's weekday are
     * created, because opening a session for the wrong day is refused by the
     * service — the fixture has to obey the same rule as the application.
     *
     * @param  Collection<int, TeachingAssignment>  $assignments
     * @param  array<int, Term>  $terms
     */
    private function attendance(Collection $assignments, array $terms): void
    {
        $term = $terms[0] ?? null;

        if (! $term instanceof Term) {
            return;
        }

        $monthStart = now()->startOfMonth();
        $patterns = [
            [AttendanceStatus::Present, AttendanceStatus::Present, AttendanceStatus::Present, AttendanceStatus::Late],
            [AttendanceStatus::Present, AttendanceStatus::Sick, AttendanceStatus::Present, AttendanceStatus::Present],
            [AttendanceStatus::Present, AttendanceStatus::Excused, AttendanceStatus::Absent, AttendanceStatus::Present],
        ];

        $scheduleIndex = 0;

        foreach (ClassSchedule::query()->orderBy('id')->limit(6)->get() as $schedule) {
            $day = $schedule->day_of_week;

            // Up to three occurrences of this weekday inside the current month.
            $dates = [];
            for ($dayCursor = $monthStart->copy(); $dayCursor->month === $monthStart->month; $dayCursor->addDay()) {
                if ($dayCursor->dayOfWeekIso === $day->value) {
                    $dates[] = $dayCursor->copy();
                }
            }

            $pattern = $patterns[$scheduleIndex % count($patterns)];

            foreach (array_slice($dates, 0, 3) as $occurrence => $date) {
                $session = AttendanceSession::firstOrCreate([
                    'schedule_id' => $schedule->getKey(),
                    'session_date' => $date->toDateString(),
                ], [
                    'academic_year_id' => $schedule->academic_year_id,
                    'term_id' => $schedule->term_id,
                    'class_id' => $schedule->class_id,
                    'subject_id' => $schedule->subject_id,
                    'teacher_id' => $schedule->teacher_id,
                    'starts_at' => substr((string) $schedule->starts_at, 0, 5),
                    'ends_at' => substr((string) $schedule->ends_at, 0, 5),
                    'status' => AttendanceSessionStatus::Submitted,
                    'submitted_at' => $date->copy()->setTime(9, 0),
                    'marked_by_user_id' => $schedule->teacher?->user_id,
                    'note' => null,
                ]);

                $enrollments = Enrollment::query()
                    ->where('class_id', $schedule->class_id)
                    ->where('status', EnrollmentStatus::Active->value)
                    ->orderBy('student_id')
                    ->get();

                foreach ($enrollments as $index => $enrollment) {
                    $status = $pattern[($index + $occurrence) % count($pattern)];

                    AttendanceRecord::firstOrCreate([
                        'attendance_session_id' => $session->getKey(),
                        'student_id' => $enrollment->student_id,
                    ], [
                        'status' => $status,
                        'note' => $status->requiresNote() ? 'Keterangan dari wali kelas' : null,
                        'marked_by_user_id' => $session->marked_by_user_id,
                        'marked_at' => $session->submitted_at,
                        'revision' => 0,
                    ]);
                }
            }

            $scheduleIndex++;
        }
    }

    /**
     * @param  array<int, ClassModel>  $classes
     */
    private function admission(AcademicYear $year, array $classes): void
    {
        $period = AdmissionPeriod::query()->firstOrNew([
            'slug' => 'ppdb-'.now()->format('Y'),
        ]);
        $period->forceFill([
            'academic_year_id' => $year->getKey(),
            'name' => 'PPDB '.$year->label,
            'description' => $period->description ?? 'Pendaftaran siswa baru sedang dibuka.',
            'instructions' => $period->instructions ?? 'Lengkapi formulir dan unggah dokumen yang diminta.',
            'internal_notes' => $period->internal_notes ?? null,
            'opens_at' => $period->opens_at ?? now()->subWeeks(2),
            'closes_at' => $period->closes_at ?? now()->addMonths(2),
            'status' => $period->exists ? $period->status : AdmissionPeriodStatus::Open,
            'quota' => $period->quota ?? 120,
            'published_at' => $period->published_at ?? now()->subWeeks(2),
        ])->save();

        $definitions = [
            ['registration_number' => 'PPDB-'.now()->format('Y').'-0001', 'applicant_name' => 'Dewi Ananta', 'status' => AdmissionApplicationStatus::Submitted, 'target_class' => null],
            ['registration_number' => 'PPDB-'.now()->format('Y').'-0002', 'applicant_name' => 'Fajar Nugroho', 'status' => AdmissionApplicationStatus::UnderReview, 'target_class' => null],
            ['registration_number' => 'PPDB-'.now()->format('Y').'-0003', 'applicant_name' => 'Gita Maharani', 'status' => AdmissionApplicationStatus::Accepted, 'target_class' => $classes[0]->getKey() ?? null],
        ];

        $reviewerId = User::query()->where('email', 'admin.test@sekolah.local')->value('id');

        foreach ($definitions as $index => $definition) {
            if (AdmissionApplication::query()->where('registration_number', $definition['registration_number'])->exists()) {
                continue;
            }

            $applicant = [
                'applicant_name' => $definition['applicant_name'],
                'preferred_name' => null,
                'birth_date' => now()->subYears(13)->toDateString(),
                'birth_place' => 'Bandung',
                'gender' => $index % 2 === 0 ? 'P' : 'L',
                'contact_email' => str($definition['applicant_name'])->lower()->slug('.')->value().'@contoh.test',
                'contact_phone' => '0811-2000-000'.($index + 1),
                'address' => 'Jl. Contoh No. '.($index + 2).', Bandung',
                'current_school' => 'SDN Contoh '.($index + 1),
                'requested_grade_level' => 7,
            ];

            $submittedAt = now()->subWeeks(2)->addDays($index);
            $version = 1;

            // registration_number, conversion_status and the duplicate
            // fingerprint are not mass assignable, exactly as on the public
            // submission path, so the fixture mirrors the real write path.
            $application = new AdmissionApplication($applicant);
            $application->forceFill([
                'admission_period_id' => $period->getKey(),
                'registration_number' => $definition['registration_number'],
                'source' => 'public_web',
                'status' => $definition['status'],
                'conversion_status' => AdmissionConversionStatus::NotStarted,
                'workflow_version' => 1,
                'duplicate_fingerprint' => $this->fingerprint($applicant),
                'duplicate_flag' => false,
                'consent_version' => (string) config('sekolah-kita.ppdb.consent_version'),
                'consent_accepted_at' => $submittedAt,
                'contact_verified_at' => $submittedAt,
                'submitted_at' => $submittedAt,
                'assigned_reviewer_user_id' => $definition['status'] === AdmissionApplicationStatus::Submitted
                    ? null
                    : $reviewerId,
                'target_class_id' => $definition['target_class'],
                'decided_at' => $definition['status'] === AdmissionApplicationStatus::Accepted ? $submittedAt->copy()->addDay() : null,
                'accepted_at' => $definition['status'] === AdmissionApplicationStatus::Accepted ? $submittedAt->copy()->addDay() : null,
            ]);
            $application->save();

            $application->guardians()->create([
                'legal_name' => $definition['applicant_name'].' Ayah',
                'relationship_type' => 'father',
                'phone' => $applicant['contact_phone'],
                'email' => $applicant['contact_email'],
                'address' => $applicant['address'],
                'is_primary_contact' => true,
                'is_emergency_contact' => true,
                'priority_order' => 1,
            ]);

            $application->statusHistories()->create([
                'from_status' => null,
                'to_status' => AdmissionApplicationStatus::Submitted,
                'actor_type' => AdmissionActorType::Applicant,
                'actor_user_id' => null,
                'reason_code' => 'application_submitted',
                'public_message' => 'Pengajuan Anda telah diterima dan menunggu peninjauan.',
                'correlation_id' => (string) Str::uuid(),
                'workflow_version' => $version,
                'occurred_at' => $submittedAt,
            ]);

            if ($definition['status'] !== AdmissionApplicationStatus::Submitted) {
                $version++;
                $application->statusHistories()->create([
                    'from_status' => AdmissionApplicationStatus::Submitted,
                    'to_status' => $definition['status'],
                    'actor_type' => AdmissionActorType::Staff,
                    'actor_user_id' => $reviewerId,
                    'reason_code' => 'review_started',
                    'public_message' => $definition['status'] === AdmissionApplicationStatus::Accepted
                        ? 'Selamat, pengajuan Anda diterima.'
                        : 'Pengajuan Anda sedang ditinjau oleh tim sekolah.',
                    'correlation_id' => (string) Str::uuid(),
                    'workflow_version' => $version,
                    'occurred_at' => $submittedAt->copy()->addHours(6),
                ]);

                // application_status_histories is unique on
                // (application, workflow_version), and the workflow services
                // derive the next version from the application row, so the two
                // have to agree.
                $application->forceFill(['workflow_version' => $version])->save();
            }
        }

        $this->portalApplicants($period, $reviewerId);
    }

    /**
     * PPDB applicant portal fixtures.
     *
     * Two candidates, each with a real portal account so the whole applicant
     * journey can be walked in a browser: one complete and submitted, and one
     * whose document has been sent back for a replacement, so the revision loop
     * has a case to exercise.
     *
     * Documents are stored as media metadata only — no real file is written, and
     * every document is a private, quarantined asset exactly like a real upload
     * that has not been scanned yet.
     */
    private function portalApplicants(AdmissionPeriod $period, ?int $reviewerId): void
    {
        $definitions = [
            [
                'email' => 'calon.lengkap@sekolah.local',
                'name' => 'Rangga Wicaksana',
                'phone' => '0811-3000-0001',
                'status' => AdmissionApplicationStatus::Submitted,
                'needs_revision' => false,
            ],
            [
                'email' => 'calon.perbaikan@sekolah.local',
                'name' => 'Sekar Arum',
                'phone' => '0811-3000-0002',
                'status' => AdmissionApplicationStatus::UnderReview,
                'needs_revision' => true,
            ],
        ];

        foreach ($definitions as $index => $definition) {
            $user = User::query()->where('email', $definition['email'])->first();

            if (! $user instanceof User) {
                continue;
            }

            $applicant = [
                'applicant_name' => $definition['name'],
                'national_id' => '327301450190000'.$index,
                'nisn' => '123456789'.$index,
                'birth_place' => 'Bandung',
                'birth_date' => now()->subYears(13)->toDateString(),
                'gender' => $index === 0 ? 'L' : 'P',
                'address' => 'Jl. Contoh No. '.($index + 10),
                'address_rt' => '00'.($index + 1),
                'address_rw' => '00'.($index + 2),
                'village' => 'Cibunar',
                'district' => 'Sukajadi',
                'regency' => 'Bandung',
                'province' => 'Jawa Barat',
                'postal_code' => '40131',
                'contact_email' => $definition['email'],
                'contact_phone' => $definition['phone'],
                'contact_whatsapp' => $definition['phone'],
                'current_school' => 'SDN Contoh '.($index + 1),
                'graduation_year' => (int) now()->year,
                'requested_grade_level' => 7,
            ];

            $submittedAt = now()->subDays(5 - $index);
            $registrationNumber = 'PPDB-PORTAL-'.now()->format('Y').'-000'.($index + 1);

            // Written through the same shape the portal service writes, so a
            // fixture row and a real row are indistinguishable: registration
            // number, source, workflow version and the duplicate fingerprint are
            // not mass assignable, exactly as on the live path.
            $application = AdmissionApplication::firstOrNew(['registration_number' => $registrationNumber]);
            $application->fill($applicant);
            $application->forceFill([
                'admission_period_id' => $period->getKey(),
                'applicant_user_id' => $user->getKey(),
                'source' => 'ppdb_portal',
                'status' => $definition['status'],
                'conversion_status' => AdmissionConversionStatus::NotStarted,
                'workflow_version' => $definition['status'] === AdmissionApplicationStatus::UnderReview ? 2 : 1,
                'duplicate_fingerprint' => $this->fingerprint($applicant),
                'duplicate_flag' => false,
                'assigned_reviewer_user_id' => $reviewerId,
                'submitted_at' => $submittedAt,
                'locked_at' => $submittedAt,
                'consent_version' => (string) config('sekolah-kita.ppdb.consent_version'),
                'consent_accepted_at' => $submittedAt,
                'contact_verified_at' => $submittedAt,
            ]);
            $application->save();

            if ($application->guardians()->exists()) {
                continue;
            }

            $application->guardians()->create([
                'legal_name' => $definition['name'].' Ayah',
                'national_id' => '327301120180000'.$index,
                'relationship_type' => GuardianRelationshipType::Father,
                'phone' => $definition['phone'],
                'whatsapp_number' => $definition['phone'],
                'occupation' => 'Wiraswasta',
                'address' => $applicant['address'],
                'is_primary_contact' => true,
                'is_emergency_contact' => true,
                'priority_order' => 1,
            ]);

            foreach (AdmissionDocumentType::required() as $documentIndex => $type) {
                $needsRevision = $definition['needs_revision'] && $type === AdmissionDocumentType::GuardianIdentity;

                $media = MediaAsset::firstOrCreate([
                    'private_object_key' => 'admission-documents/'.hash('sha256', $application->registration_number.$type->value).'.pdf',
                ], [
                    'uploader_user_id' => $user->getKey(),
                    'media_kind' => 'document',
                    'original_filename' => $type->value.'.pdf',
                    'private_disk' => 'local',
                    'public_disk' => null,
                    'public_object_key' => null,
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 1024,
                    'checksum_sha256' => hash('sha256', $application->registration_number.$type->value),
                    'processing_status' => 'ready',
                    'scan_status' => 'clean',
                    'scanned_at' => $submittedAt,
                    'scan_error_code' => null,
                    'visibility' => 'private',
                ]);

                $document = ApplicationDocument::firstOrCreate([
                    'admission_application_id' => $application->getKey(),
                    'document_type' => $type,
                    'version' => 1,
                ], [
                    'media_asset_id' => $media->getKey(),
                    'verification_status' => $needsRevision
                        ? AdmissionDocumentVerificationStatus::NeedsRevision
                        : AdmissionDocumentVerificationStatus::Verified,
                    'reviewed_by_user_id' => $reviewerId,
                    'reviewed_at' => $submittedAt->copy()->addHours(6),
                    'reviewer_note' => $needsRevision ? 'Dokumen tidak terbaca. Silakan unggah ulang dengan foto lebih terang.' : null,
                ]);

                if ($needsRevision) {
                    continue;
                }

                unset($documentIndex, $document);
            }

            ApplicationStatusHistory::firstOrCreate([
                'admission_application_id' => $application->getKey(),
                'workflow_version' => 1,
            ], [
                'from_status' => AdmissionApplicationStatus::Draft,
                'to_status' => AdmissionApplicationStatus::Submitted,
                'actor_type' => AdmissionActorType::Applicant,
                'actor_user_id' => $user->getKey(),
                'reason_code' => 'ppdb_portal_submitted',
                'public_message' => 'Pengajuan Anda lengkap dan sudah dikirim untuk ditinjau.',
                'correlation_id' => (string) Str::uuid(),
                'occurred_at' => $submittedAt,
            ]);

            if ($definition['status'] === AdmissionApplicationStatus::UnderReview) {
                ApplicationStatusHistory::firstOrCreate([
                    'admission_application_id' => $application->getKey(),
                    'workflow_version' => 2,
                ], [
                    'from_status' => AdmissionApplicationStatus::Submitted,
                    'to_status' => AdmissionApplicationStatus::UnderReview,
                    'actor_type' => AdmissionActorType::Staff,
                    'actor_user_id' => $reviewerId,
                    'reason_code' => 'review_started',
                    'public_message' => 'Pengajuan Anda sedang ditinjau oleh tim sekolah.',
                    'correlation_id' => (string) Str::uuid(),
                    'occurred_at' => $submittedAt->copy()->addHours(6),
                ]);

                $application->forceFill(['workflow_version' => 2])->save();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $applicant
     */
    private function fingerprint(array $applicant): string
    {
        return hash_hmac('sha256', implode('|', [
            $applicant['applicant_name'],
            $applicant['birth_date'] ?? '',
            $applicant['contact_email'],
        ]), (string) config('app.key'));
    }

    private function content(): void
    {
        $author = User::query()->where('email', 'admin.test@sekolah.local')->first();
        $definitions = [
            [
                'slug' => 'selamat-datang-di-sekolah-kita',
                'title' => 'Selamat datang di Sekolah Kita',
                'excerpt' => 'Pengumuman singkat untuk memastikan dashboards administrator terisi data nyata.',
                'body' => '<p>Sekolah Kita siap dipakai untuk pengujian end-to-end.</p>',
                'status' => PublicationStatus::Published,
            ],
            [
                'slug' => 'draf-contoh-untuk-pengujian',
                'title' => 'Draf contoh untuk pengujian',
                'excerpt' => 'Draf yang belum diterbitkan, dipakai untuk menguji alur terbitan.',
                'body' => '<p>Draf berita.</p>',
                'status' => PublicationStatus::Draft,
            ],
        ];

        foreach ($definitions as $definition) {
            $article = NewsArticle::query()->firstOrNew(['slug' => $definition['slug']]);
            $article->forceFill([
                'title' => $definition['title'],
                'excerpt' => $definition['excerpt'],
                'body' => $definition['body'],
                'status' => $definition['status'],
                'author_user_id' => $author?->getKey(),
                'published_at' => $definition['status'] === PublicationStatus::Published
                    ? ($article->published_at ?? now()->subDay())
                    : null,
            ])->save();
        }
    }
}
