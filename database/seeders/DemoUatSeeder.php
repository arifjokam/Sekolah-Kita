<?php

namespace Database\Seeders;

use App\Enums\AcademicYearStatus;
use App\Enums\AdmissionActorType;
use App\Enums\AdmissionApplicationStatus;
use App\Enums\AdmissionConversionStatus;
use App\Enums\AdmissionPeriodStatus;
use App\Enums\AssignmentStatus;
use App\Enums\ClassStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\GuardianLifecycleStatus;
use App\Enums\GuardianRelationshipType;
use App\Enums\PublicationStatus;
use App\Enums\StudentLifecycleStatus;
use App\Enums\TeacherEmploymentStatus;
use App\Enums\TermStatus;
use App\Models\AcademicYear;
use App\Models\AdmissionApplication;
use App\Models\AdmissionPeriod;
use App\Models\ClassModel;
use App\Models\ClassTeacherAssignment;
use App\Models\Guardian;
use App\Models\NewsArticle;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;
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
        $this->assignments($teachers, $classes, $subjects, $activeYear, $terms);
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
     */
    private function assignments(array $teachers, array $classes, array $subjects, AcademicYear $year, array $terms): void
    {
        $start = now()->subMonths(5)->toDateString();

        foreach ($teachers as $index => $teacher) {
            foreach ($subjects as $offset => $subject) {
                $class = $classes[($index + $offset) % count($classes)];

                TeachingAssignment::query()->firstOrCreate([
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

            ClassTeacherAssignment::query()->firstOrCreate([
                'class_id' => $class->getKey(),
                'teacher_id' => $teacher->getKey(),
            ], [
                'status' => AssignmentStatus::Active,
                'starts_on' => $start,
                'ends_on' => null,
                'internal_reason' => null,
            ]);
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
