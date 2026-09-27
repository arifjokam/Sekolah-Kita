<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\AttendanceSessionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\ClassStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\ScheduleStatus;
use App\Enums\TermStatus;
use App\Enums\UserRole;
use App\Enums\WeekDay;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassModel;
use App\Models\ClassSchedule;
use App\Models\ClassTeacherAssignment;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Digital attendance.
 *
 * The security rule this file exists to prove: a teacher may only mark attendance
 * for a class they are actually assigned to, in a lesson they are actually
 * timetabled for. A session id from another teacher's timetable is rejected in
 * the policy, before any query on its records runs, and a student can never read
 * another student's register.
 */
class AttendanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * A complete, teachable setup: an active year, one term, one class, one
     * subject, one teacher assigned to teach it, and three enrolled students.
     *
     * @return array{year: AcademicYear, term: Term, class: ClassModel, subject: Subject, teacher: Teacher, user: User, students: list<Student>}
     */
    private function scenario(?Carbon $day = null): array
    {
        $day ??= Carbon::parse('2026-09-23'); // a Wednesday
        Carbon::setTestNow($day->copy()->setTime(7, 30));

        $year = AcademicYear::factory()->create([
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'status' => 'active',
        ]);

        $term = Term::factory()->create([
            'academic_year_id' => $year->getKey(),
            'sequence' => 1,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-12-31',
            'status' => TermStatus::Active,
        ]);

        $class = ClassModel::factory()->create([
            'academic_year_id' => $year->getKey(),
            'grade_level' => 7,
            'name' => 'A',
            'status' => ClassStatus::Active,
        ]);

        $subject = Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']);
        $teacher = Teacher::factory()->create(['legal_name' => 'Guru Absen']);

        TeachingAssignment::factory()->create([
            'teacher_id' => $teacher->getKey(),
            'class_id' => $class->getKey(),
            'subject_id' => $subject->getKey(),
            'academic_year_id' => $year->getKey(),
            'term_id' => $term->getKey(),
            'status' => AssignmentStatus::Active,
            'starts_on' => '2026-07-01',
            'ends_on' => null,
        ]);

        $user = User::factory()->guru()->create();
        $teacher->forceFill(['user_id' => $user->getKey()])->save();

        $students = collect(range(1, 3))
            ->map(fn (int $index): Student => Student::factory()->create([
                'legal_name' => 'Siswa '.$index,
                // The factory fills preferred_name randomly, and the screens
                // render getDisplayName(), so pin it for assertable output.
                'preferred_name' => null,
                'student_number' => 'NIS-'.$index,
            ]))
            ->all();

        foreach ($students as $student) {
            Enrollment::factory()->active()->create([
                'student_id' => $student->getKey(),
                'class_id' => $class->getKey(),
                'academic_year_id' => $year->getKey(),
                'status' => EnrollmentStatus::Active,
            ]);
        }

        return compact('year', 'term', 'class', 'subject', 'teacher', 'user', 'students') + ['day' => $day];
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function schedule(array $scope, WeekDay $day = WeekDay::Wednesday, ?string $start = null, ?string $end = null): ClassSchedule
    {
        $start ??= '07:00';
        $end ??= '08:30';

        return ClassSchedule::factory()->create([
            'academic_year_id' => $scope['year']->getKey(),
            'term_id' => $scope['term']->getKey(),
            'class_id' => $scope['class']->getKey(),
            'subject_id' => $scope['subject']->getKey(),
            'teacher_id' => $scope['teacher']->getKey(),
            'day_of_week' => $day->value,
            'starts_at' => $start,
            'ends_at' => $end,
            'room' => 'R. 101',
            'status' => ScheduleStatus::Active,
        ]);
    }

    /**
     * @return array<int, array{status: string, note: string|null}>
     */
    private function marks(array $scope, array $overrides = []): array
    {
        $marks = [];

        foreach ($scope['students'] as $index => $student) {
            $marks[$student->getKey()] = $overrides[$student->getKey()] ?? ['status' => AttendanceStatus::Present->value];
        }

        return $marks;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Opening a session
    |--------------------------------------------------------------------------
    */

    public function test_a_teacher_opens_todays_lesson_and_sees_the_roster(): void
    {
        $scope = $this->scenario();
        $schedule = $this->schedule($scope);

        $this->actingAs($scope['user'])
            ->get(route('guru.absensi.index'))
            ->assertOk()
            ->assertSee('Mulai absensi')
            ->assertSee('R. 101')
            ->assertSee('Matematika');

        $this->actingAs($scope['user'])
            ->post(route('guru.absensi.open', $schedule))
            ->assertRedirect();

        $session = AttendanceSession::query()->firstOrFail();

        $this->assertSame($schedule->getKey(), (int) $session->schedule_id);
        $this->assertSame(AttendanceSessionStatus::Open, $session->status);
        $this->assertSame($scope['day']->toDateString(), $session->session_date->toDateString());
        $this->assertSame($scope['class']->getKey(), (int) $session->class_id);
        $this->assertSame($scope['subject']->getKey(), (int) $session->subject_id);

        $this->actingAs($scope['user'])
            ->get(route('guru.absensi.show', $session))
            ->assertOk()
            ->assertSee('Siswa 1')
            ->assertSee('Hadir')
            ->assertSee('Terlambat')
            ->assertSee('Izin')
            ->assertSee('Sakit')
            ->assertSee('Alpa');
    }

    public function test_opening_the_same_lesson_twice_returns_the_same_session(): void
    {
        $scope = $this->scenario();
        $schedule = $this->schedule($scope);

        $this->actingAs($scope['user'])->post(route('guru.absensi.open', $schedule))->assertRedirect();
        $first = AttendanceSession::query()->firstOrFail();

        $this->actingAs($scope['user'])->post(route('guru.absensi.open', $schedule))->assertRedirect();

        $this->assertSame(1, AttendanceSession::query()->count(), 'A lesson produces one session per date.');
        $this->assertSame($first->getKey(), AttendanceSession::query()->firstOrFail()->getKey());
    }

    public function test_a_lesson_cannot_be_opened_for_a_day_it_does_not_occur_on(): void
    {
        $scope = $this->scenario();
        $schedule = $this->schedule($scope, WeekDay::Monday);

        $this->actingAs($scope['user'])
            ->post(route('guru.absensi.open', $schedule))
            ->assertSessionHasErrors('date');

        $this->assertSame(0, AttendanceSession::query()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Saving attendance
    |--------------------------------------------------------------------------
    */

    public function test_a_teacher_saves_the_sheet(): void
    {
        $scope = $this->scenario();
        $schedule = $this->schedule($scope);
        $this->actingAs($scope['user'])->post(route('guru.absensi.open', $schedule));
        $session = AttendanceSession::query()->firstOrFail();

        $marks = $this->marks($scope, [
            $scope['students'][1]->getKey() => ['status' => AttendanceStatus::Late->value],
            $scope['students'][2]->getKey() => ['status' => AttendanceStatus::Sick->value, 'note' => 'Surat dokter'],
        ]);

        $this->actingAs($scope['user'])
            ->post(route('guru.absensi.store', $session), ['records' => $marks])
            ->assertRedirect(route('guru.absensi.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(AttendanceSessionStatus::Submitted, $session->refresh()->status);
        $this->assertNotNull($session->submitted_at);
        $this->assertSame($scope['user']->getKey(), (int) $session->marked_by_user_id);

        $this->assertSame(3, AttendanceRecord::query()->count());

        $late = AttendanceRecord::query()->where('student_id', $scope['students'][1]->getKey())->firstOrFail();
        $this->assertSame(AttendanceStatus::Late, $late->status);
        $this->assertSame(0, (int) $late->revision);

        $sick = AttendanceRecord::query()->where('student_id', $scope['students'][2]->getKey())->firstOrFail();
        $this->assertSame(AttendanceStatus::Sick, $sick->status);
        $this->assertSame('Surat dokter', $sick->note);
        $this->assertNotNull($sick->marked_at);
    }

    public function test_a_submitted_session_is_corrected_through_the_correction_path(): void
    {
        $scope = $this->scenario();
        $schedule = $this->schedule($scope);
        $this->actingAs($scope['user'])->post(route('guru.absensi.open', $schedule));
        $session = AttendanceSession::query()->firstOrFail();

        $this->actingAs($scope['user'])
            ->post(route('guru.absensi.store', $session), ['records' => $this->marks($scope)])
            ->assertSessionHasNoErrors();

        // The plain save route is closed once a sheet is submitted, so a
        // correction is always an explicit, separately audited action.
        $this->actingAs($scope['user'])
            ->post(route('guru.absensi.store', $session), ['records' => $this->marks($scope)])
            ->assertForbidden();

        $this->actingAs($scope['user'])
            ->put(route('guru.absensi.correct', $session), [
                'records' => $this->marks($scope, [
                    $scope['students'][0]->getKey() => ['status' => AttendanceStatus::Absent->value, 'note' => 'Tanpa keterangan'],
                ]),
            ])
            ->assertRedirect(route('guru.absensi.show', $session))
            ->assertSessionHasNoErrors();

        $session->refresh();
        $this->assertSame(AttendanceSessionStatus::Corrected, $session->status);

        $record = AttendanceRecord::query()->where('student_id', $scope['students'][0]->getKey())->firstOrFail();
        $this->assertSame(AttendanceStatus::Absent, $record->status);
        $this->assertSame(1, (int) $record->revision, 'A correction increments the record revision.');

        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance_session.corrected']);
    }

    public function test_a_submitted_sheet_cannot_be_edited_by_another_teacher(): void
    {
        $scope = $this->scenario();
        $schedule = $this->schedule($scope);
        $this->actingAs($scope['user'])->post(route('guru.absensi.open', $schedule));
        $session = AttendanceSession::query()->firstOrFail();
        $this->actingAs($scope['user'])
            ->post(route('guru.absensi.store', $session), ['records' => $this->marks($scope)])
            ->assertSessionHasNoErrors();

        $intruder = Teacher::factory()->create();
        $intruderUser = User::factory()->guru()->create();
        $intruder->forceFill(['user_id' => $intruderUser->getKey()])->save();

        $this->actingAs($intruderUser)->get(route('guru.absensi.show', $session))->assertForbidden();
        $this->actingAs($intruderUser)
            ->put(route('guru.absensi.correct', $session), ['records' => $this->marks($scope)])
            ->assertForbidden();
    }

    public function test_a_student_from_another_class_cannot_be_injected_into_a_sheet(): void
    {
        $scope = $this->scenario();
        $schedule = $this->schedule($scope);
        $this->actingAs($scope['user'])->post(route('guru.absensi.open', $schedule));
        $session = AttendanceSession::query()->firstOrFail();

        $outsider = Student::factory()->create();

        $this->actingAs($scope['user'])
            ->post(route('guru.absensi.store', $session), [
                'records' => [
                    ...$this->marks($scope),
                    $outsider->getKey() => ['status' => AttendanceStatus::Present->value],
                ],
            ])
            ->assertSessionHasErrors('records');

        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_a_status_without_a_reason_is_refused(): void
    {
        $scope = $this->scenario();
        $schedule = $this->schedule($scope);
        $this->actingAs($scope['user'])->post(route('guru.absensi.open', $schedule));
        $session = AttendanceSession::query()->firstOrFail();

        $marks = $this->marks($scope, [
            $scope['students'][2]->getKey() => ['status' => AttendanceStatus::Sick->value, 'note' => ''],
        ]);

        $this->actingAs($scope['user'])
            ->post(route('guru.absensi.store', $session), ['records' => $marks])
            ->assertSessionHasErrors('records.'.$scope['students'][2]->getKey().'.note');

        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_an_invalid_status_is_refused(): void
    {
        $scope = $this->scenario();
        $schedule = $this->schedule($scope);
        $this->actingAs($scope['user'])->post(route('guru.absensi.open', $schedule));
        $session = AttendanceSession::query()->firstOrFail();

        $this->actingAs($scope['user'])
            ->post(route('guru.absensi.store', $session), [
                'records' => [$scope['students'][0]->getKey() => ['status' => 'maybe']],
            ])
            ->assertSessionHasErrors('records.'.$scope['students'][0]->getKey().'.status');
    }

    public function test_a_double_submit_does_not_create_duplicate_records(): void
    {
        $scope = $this->scenario();
        $schedule = $this->schedule($scope);
        $this->actingAs($scope['user'])->post(route('guru.absensi.open', $schedule));
        $session = AttendanceSession::query()->firstOrFail();

        $this->actingAs($scope['user'])
            ->post(route('guru.absensi.store', $session), ['records' => $this->marks($scope)])
            ->assertSessionHasNoErrors();

        $this->actingAs($scope['user'])
            ->put(route('guru.absensi.correct', $session), ['records' => $this->marks($scope)])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, AttendanceRecord::query()->count());
    }

    public function test_an_empty_roster_cannot_be_saved(): void
    {
        $scope = $this->scenario();
        // Remove the enrolment so the class has nobody to mark.
        Enrollment::query()->delete();

        $schedule = $this->schedule($scope);
        $this->actingAs($scope['user'])->post(route('guru.absensi.open', $schedule));
        $session = AttendanceSession::query()->firstOrFail();

        $this->actingAs($scope['user'])
            ->post(route('guru.absensi.store', $session), ['records' => []])
            ->assertSessionHasErrors('records');
    }

    public function test_a_homeroom_teacher_may_mark_a_session_they_do_not_teach(): void
    {
        $scope = $this->scenario();

        // A second teacher who is only the homeroom teacher of the class.
        $homeroomTeacher = Teacher::factory()->create();
        $homeroomUser = User::factory()->guru()->create();
        $homeroomTeacher->forceFill(['user_id' => $homeroomUser->getKey()])->save();

        ClassTeacherAssignment::factory()->create([
            'class_id' => $scope['class']->getKey(),
            'teacher_id' => $homeroomTeacher->getKey(),
            'status' => AssignmentStatus::Active,
            'starts_on' => '2026-07-01',
            'ends_on' => null,
        ]);

        $schedule = $this->schedule($scope);
        $this->actingAs($scope['user'])->post(route('guru.absensi.open', $schedule));
        $session = AttendanceSession::query()->firstOrFail();

        // The session names the subject teacher, but the homeroom teacher is
        // accountable for the register, so they may correct it.
        $this->actingAs($homeroomUser)
            ->put(route('guru.absensi.correct', $session), ['records' => $this->marks($scope)])
            ->assertSessionHasNoErrors();

        // Correcting it does not reassign who teaches the lesson either.
        $this->assertSame($scope['teacher']->getKey(), (int) $session->refresh()->teacher_id);
    }

    public function test_the_session_records_the_timetabled_teacher_not_whoever_opened_it(): void
    {
        $scope = $this->scenario();

        $homeroomTeacher = Teacher::factory()->create();
        $homeroomUser = User::factory()->guru()->create();
        $homeroomTeacher->forceFill(['user_id' => $homeroomUser->getKey()])->save();

        ClassTeacherAssignment::factory()->create([
            'class_id' => $scope['class']->getKey(),
            'teacher_id' => $homeroomTeacher->getKey(),
            'status' => AssignmentStatus::Active,
            'starts_on' => '2026-07-01',
            'ends_on' => null,
        ]);

        $schedule = $this->schedule($scope);

        /*
         * The homeroom teacher opens the register for a subject they do not teach.
         * The session's teacher_id is a snapshot of the timetable, not of the
         * button press: writing the actor here made the lesson disappear from the
         * actual subject teacher's list forever, because todaysLessons filters on
         * that column, and the session would claim the wrong person taught it.
         */
        $this->actingAs($homeroomUser)
            ->post(route('guru.absensi.open', $schedule))
            ->assertRedirect();

        $session = AttendanceSession::query()->firstOrFail();

        $this->assertSame($schedule->getKey(), (int) $session->schedule_id);
        $this->assertSame(
            $scope['teacher']->getKey(),
            (int) $session->teacher_id,
            'The session snapshots the timetabled teacher.'
        );
        $this->assertNotSame($homeroomTeacher->getKey(), (int) $session->teacher_id);

        // The lesson therefore still belongs to the subject teacher's own list.
        $this->actingAs($scope['user'])
            ->get(route('guru.absensi.index'))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertSee('Lanjut isi');
    }

    public function test_a_teacher_cannot_open_a_session_for_a_class_they_do_not_teach(): void
    {
        $scope = $this->scenario();
        $schedule = $this->schedule($scope);

        $stranger = Teacher::factory()->create();
        $strangerUser = User::factory()->guru()->create();
        $stranger->forceFill(['user_id' => $strangerUser->getKey()])->save();

        $this->actingAs($strangerUser)
            ->post(route('guru.absensi.open', $schedule))
            ->assertForbidden();

        $this->assertSame(0, AttendanceSession::query()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Monthly report
    |--------------------------------------------------------------------------
    */

    public function test_a_teacher_reads_a_monthly_report_for_their_own_class(): void
    {
        $scope = $this->scenario();
        $schedule = $this->schedule($scope);

        // Three separate sessions in September, each filled through the real save
        // path so the report is built from what a teacher actually wrote.
        foreach ([[AttendanceStatus::Present, null], [AttendanceStatus::Late, null], [AttendanceStatus::Absent, 'Tanpa keterangan']] as $index => [$status, $note]) {
            $session = AttendanceSession::factory()->create([
                'schedule_id' => $schedule->getKey(),
                'academic_year_id' => $scope['year']->getKey(),
                'term_id' => $scope['term']->getKey(),
                'class_id' => $scope['class']->getKey(),
                'subject_id' => $scope['subject']->getKey(),
                'teacher_id' => $scope['teacher']->getKey(),
                'session_date' => $scope['day']->copy()->startOfMonth()->addDays(2 + ($index * 2))->toDateString(),
            ]);

            $this->actingAs($scope['user'])
                ->post(route('guru.absensi.store', $session), [
                    'records' => $this->marks($scope, [
                        $scope['students'][0]->getKey() => ['status' => $status->value, 'note' => $note],
                    ]),
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(3, AttendanceSession::query()->count());

        $this->actingAs($scope['user'])
            ->get(route('guru.absensi.report', ['class_id' => $scope['class']->getKey(), 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('Laporan absensi bulanan')
            ->assertSee('Siswa 1')
            ->assertSee('Hadir')
            ->assertSee('Alpa');

        $report = app(AttendanceService::class)
            ->monthlyClassReport($scope['teacher'], $scope['class'], Carbon::parse('2026-09'));

        // Each row is {student, counts}; match on the student's key, not the row.
        $first = collect($report['students'])->first(
            fn (array $row): bool => $row['student']->getKey() === $scope['students'][0]->getKey(),
        );

        $this->assertNotNull($first);
        $this->assertSame(3, $first['counts']['total']);
        $this->assertSame(1, $first['counts']['present']);
        $this->assertSame(1, $first['counts']['late']);
        $this->assertSame(1, $first['counts']['absent']);
        // Hadir + Terlambat over 3 sessions.
        $this->assertEqualsWithDelta(66.7, (float) $first['counts']['percentage'], 0.1);
    }

    public function test_a_teacher_cannot_report_on_a_class_they_do_not_teach(): void
    {
        $scope = $this->scenario();

        $foreignClass = ClassModel::factory()->create([
            'academic_year_id' => $scope['year']->getKey(),
            'grade_level' => 8,
            'name' => 'B',
            'status' => ClassStatus::Active,
        ]);

        $this->actingAs($scope['user'])
            ->get(route('guru.absensi.report', ['class_id' => $foreignClass->getKey(), 'month' => '2026-09']))
            ->assertOk()
            // The class is not in the teacher's own list, so it is not offered as
            // a selection and the report stays empty rather than showing rows the
            // teacher has no authority over.
            ->assertSee('Pilih kelas')
            ->assertDontSee('Siswa 1');

        $this->assertFalse(
            $scope['user']->can('view', $foreignClass),
            'A teacher outside the assignment cannot even read the class record.'
        );
    }

    public function test_the_report_is_printable(): void
    {
        $scope = $this->scenario();

        $this->actingAs($scope['user'])
            ->get(route('guru.absensi.report.print', ['class_id' => $scope['class']->getKey(), 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('Laporan absensi bulanan');
    }

    public function test_a_percentage_is_zero_rather_than_a_division_by_zero(): void
    {
        $scope = $this->scenario();

        $report = app(AttendanceService::class)
            ->monthlyClassReport($scope['teacher'], $scope['class'], Carbon::parse('2026-09'));

        $this->assertSame(0, $report['session_count']);
        foreach ($report['students'] as $row) {
            $this->assertSame(0.0, (float) $row['counts']['percentage']);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Student summary and privacy
    |--------------------------------------------------------------------------
    */

    public function test_a_student_sees_only_their_own_attendance_summary(): void
    {
        $scope = $this->scenario();
        $schedule = $this->schedule($scope);
        $this->actingAs($scope['user'])->post(route('guru.absensi.open', $schedule));
        $session = AttendanceSession::query()->firstOrFail();

        $marks = $this->marks($scope, [
            $scope['students'][0]->getKey() => ['status' => AttendanceStatus::Present->value],
            $scope['students'][1]->getKey() => ['status' => AttendanceStatus::Sick->value, 'note' => 'Surat dokter'],
            $scope['students'][2]->getKey() => ['status' => AttendanceStatus::Present->value],
        ]);
        $this->actingAs($scope['user'])
            ->post(route('guru.absensi.store', $session), ['records' => $marks])
            ->assertSessionHasNoErrors();

        $studentUser = User::factory()->create();
        $scope['students'][1]->forceFill(['user_id' => $studentUser->getKey()])->save();
        $studentUser->forceFill(['role' => UserRole::Siswa])->save();

        $this->actingAs($studentUser->refresh())
            ->get(route('siswa.absensi.index'))
            ->assertOk()
            ->assertSee('Absensi saya')
            ->assertSee('Surat dokter')
            ->assertSee('Sakit');

        $summary = app(AttendanceService::class)
            ->studentSummary($scope['students'][1]);

        $this->assertSame(1, $summary['totals']['sick']);
        $this->assertSame(0, $summary['totals']['present'], "Another student's record must not leak in.");
        $this->assertEqualsWithDelta(0.0, $summary['percentage'], 0.01);
    }

    public function test_a_student_cannot_read_another_students_attendance(): void
    {
        $scope = $this->scenario();

        $mine = User::factory()->create();
        $scope['students'][0]->forceFill(['user_id' => $mine->getKey()])->save();
        $mine->forceFill(['role' => UserRole::Siswa])->save();

        $theirs = User::factory()->create();
        $scope['students'][1]->forceFill(['user_id' => $theirs->getKey()])->save();
        $theirs->forceFill(['role' => UserRole::Siswa])->save();

        $schedule = $this->schedule($scope);
        $this->actingAs($scope['user'])->post(route('guru.absensi.open', $schedule));
        $session = AttendanceSession::query()->firstOrFail();
        $this->actingAs($scope['user'])
            ->post(route('guru.absensi.store', $session), ['records' => $this->marks($scope)])
            ->assertSessionHasNoErrors();

        $this->actingAs($mine->refresh())->get(route('siswa.absensi.index'))->assertOk()->assertDontSee('Siswa 2');
        $this->actingAs($theirs->refresh())->get(route('siswa.absensi.index'))->assertOk()->assertDontSee('Siswa 1');
    }

    public function test_a_student_cannot_reach_the_teacher_attendance_screens(): void
    {
        $scope = $this->scenario();

        $schedule = $this->schedule($scope);
        $this->actingAs($scope['user'])->post(route('guru.absensi.open', $schedule));
        $session = AttendanceSession::query()->firstOrFail();

        $studentUser = User::factory()->create();
        $scope['students'][0]->forceFill(['user_id' => $studentUser->getKey()])->save();
        $studentUser->forceFill(['role' => UserRole::Siswa])->save();

        $this->actingAs($studentUser->refresh())->get(route('guru.absensi.index'))->assertForbidden();
        $this->actingAs($studentUser->refresh())->get(route('guru.absensi.show', $session))->assertForbidden();
        $this->actingAs($studentUser->refresh())->get(route('guru.absensi.report'))->assertForbidden();
    }

    public function test_an_administrator_may_audit_attendance_but_never_marks_it(): void
    {
        $scope = $this->scenario();
        $schedule = $this->schedule($scope);
        $this->actingAs($scope['user'])->post(route('guru.absensi.open', $schedule));
        $session = AttendanceSession::query()->firstOrFail();

        $admin = User::factory()->administrator()->create();

        // The teacher screens are role:guru, so the role middleware refuses an
        // administrator outright — not even a read-only view leaks through.
        $this->actingAs($admin)->get(route('guru.absensi.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('guru.absensi.show', $session))->assertForbidden();
        $this->actingAs($admin)->get(route('guru.absensi.report'))->assertForbidden();

        $this->assertTrue($admin->can('view', $session), 'The policy still resolves read scope for an audit.');
        $this->assertFalse($admin->can('mark', $session), 'An administrator may audit, not mark.');
    }

    public function test_the_session_records_the_teacher_the_marks_were_actually_written_by(): void
    {
        $scope = $this->scenario();
        $schedule = $this->schedule($scope);
        $this->actingAs($scope['user'])->post(route('guru.absensi.open', $schedule));
        $session = AttendanceSession::query()->firstOrFail();

        $this->actingAs($scope['user'])
            ->post(route('guru.absensi.store', $session), ['records' => $this->marks($scope)])
            ->assertSessionHasNoErrors();

        $record = AttendanceRecord::query()->firstOrFail();

        $this->assertSame($scope['user']->getKey(), (int) $record->marked_by_user_id);
        $this->assertSame($scope['teacher']->getKey(), (int) $record->session->teacher_id);
    }

    public function test_the_register_renders_in_a_fixed_number_of_queries(): void
    {
        $scope = $this->scenario();

        // Six lessons on the same day, so the per-lesson pattern is visible.
        foreach (range(1, 5) as $offset) {
            $this->schedule($scope, WeekDay::from(3), sprintf('%02d:00', 7 + $offset));
        }

        $this->actingAs($scope['user'])->get(route('guru.absensi.index'))->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->actingAs($scope['user'])
                ->get(route('guru.absensi.index'))
                ->assertOk()
                ->assertSee('R. 101');

            $queries = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }

        /*
         * The register used to run one AttendanceSession query per lesson inside
         * a @foreach, plus a full Term scan with a lazy academicYear per row, so
         * its query count grew with the teacher's timetable. It is now a constant:
         * the sessions for the day are prefetched as one keyed map and the terms
         * are eager loaded. The bound is generous so the test fails on an
         * accidental N+1 rather than on an unrelated extra query.
         */
        $this->assertLessThanOrEqual(
            18,
            $queries,
            sprintf('The register ran %d queries for six lessons; a per-row lookup has crept back in.', $queries),
        );
    }
}
