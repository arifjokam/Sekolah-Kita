<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\ScheduleStatus;
use App\Enums\UserRole;
use App\Enums\WeekDay;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\ClassSchedule;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The weekly class schedule and who may see it.
 *
 * Three things are load-bearing here:
 *
 *  1. the three conflict rules — a class, a teacher and a room cannot be double
 *     booked, and the checks live in the write path so no request can bypass them;
 *  2. cross-scope integrity — a lesson may not straddle two academic years, and
 *     its teacher must actually be assigned in that semester;
 *  3. read scope — a teacher sees the lessons they are timetabled for, a student
 *     sees only their own class's lessons, and neither is widened by anything in
 *     a URL.
 */
class ClassScheduleTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array{year: AcademicYear, term: Term, class: ClassModel, subject: Subject, teacher: Teacher}
     */
    private function academicScope(?Teacher $teacher = null): array
    {
        $year = AcademicYear::factory()->active()->create([
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
        ]);

        $term = Term::factory()->create([
            'academic_year_id' => $year->getKey(),
            'sequence' => 1,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-12-31',
        ]);

        $class = ClassModel::factory()->create([
            'academic_year_id' => $year->getKey(),
            'grade_level' => 7,
            'name' => 'A',
        ]);

        // A unique code per call: several tests build a second, independent
        // academic scope in the same database.
        $subject = Subject::factory()->create([
            'code' => 'MTK'.substr(strtoupper(bin2hex(random_bytes(2))), 0, 5),
            'name' => 'Matematika',
        ]);
        $teacher ??= Teacher::factory()->create(['legal_name' => 'Guru Satu']);

        TeachingAssignment::factory()->create([
            'teacher_id' => $teacher->getKey(),
            'class_id' => $class->getKey(),
            'subject_id' => $subject->getKey(),
            'academic_year_id' => $year->getKey(),
            'term_id' => $term->getKey(),
            'status' => AssignmentStatus::Active,
        ]);

        return compact('year', 'term', 'class', 'subject', 'teacher');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $scope, array $overrides = []): array
    {
        return array_merge([
            'academic_year_id' => $scope['year']->getKey(),
            'term_id' => $scope['term']->getKey(),
            'class_id' => $scope['class']->getKey(),
            'subject_id' => $scope['subject']->getKey(),
            'teacher_id' => $scope['teacher']->getKey(),
            'day_of_week' => WeekDay::Monday->value,
            'starts_at' => '07:00',
            'ends_at' => '08:30',
            'room' => 'R. 101',
            'status' => ScheduleStatus::Active->value,
        ], $overrides);
    }

    private function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    private function teacherUser(Teacher $teacher): User
    {
        $user = User::factory()->guru()->create();
        $teacher->forceFill(['user_id' => $user->getKey()])->save();

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | Administrator writes
    |--------------------------------------------------------------------------
    */

    public function test_an_administrator_creates_a_weekly_lesson(): void
    {
        $admin = $this->admin();
        $scope = $this->academicScope();

        $this->actingAs($admin)
            ->post(route('admin.class-schedules.store'), $this->payload($scope))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $schedule = ClassSchedule::query()->firstOrFail();

        $this->assertTrue($schedule->isActive());
        $this->assertSame(WeekDay::Monday, $schedule->day_of_week);
        $this->assertSame('07:00', substr((string) $schedule->starts_at, 0, 5));
        $this->assertSame(90, $schedule->durationMinutes());

        $this->actingAs($admin)->get(route('admin.class-schedules.index'))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertSee('Senin');

        $this->actingAs($admin)->get(route('admin.class-schedules.show', $schedule))->assertOk()->assertSee('R. 101');
    }

    public function test_a_class_cannot_have_two_lessons_at_the_same_time(): void
    {
        $admin = $this->admin();
        $scope = $this->academicScope();

        $this->actingAs($admin)->post(route('admin.class-schedules.store'), $this->payload($scope))
            ->assertSessionHasNoErrors();

        // Overlapping on the same day and class.
        $this->actingAs($admin)
            ->post(route('admin.class-schedules.store'), $this->payload($scope, [
                'starts_at' => '08:00',
                'ends_at' => '09:00',
                'room' => 'R. 102',
            ]))
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(1, ClassSchedule::query()->count());
    }

    public function test_back_to_back_lessons_in_the_same_class_are_allowed(): void
    {
        $admin = $this->admin();
        $scope = $this->academicScope();

        $this->actingAs($admin)->post(route('admin.class-schedules.store'), $this->payload($scope))
            ->assertSessionHasNoErrors();

        // Ending exactly when the next one starts is a legal timetable, not a clash.
        $this->actingAs($admin)
            ->post(route('admin.class-schedules.store'), $this->payload($scope, [
                'starts_at' => '08:30',
                'ends_at' => '10:00',
                'room' => 'R. 102',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, ClassSchedule::query()->count());
    }

    public function test_a_teacher_cannot_teach_two_classes_at_the_same_time(): void
    {
        $admin = $this->admin();
        $scope = $this->academicScope();
        $other = $this->academicScope($scope['teacher']);

        // The second class needs its own assignment for the same teacher.
        TeachingAssignment::factory()->create([
            'teacher_id' => $scope['teacher']->getKey(),
            'class_id' => $other['class']->getKey(),
            'subject_id' => $other['subject']->getKey(),
            'academic_year_id' => $other['year']->getKey(),
            'term_id' => $other['term']->getKey(),
            'status' => AssignmentStatus::Active,
        ]);

        $this->actingAs($admin)->post(route('admin.class-schedules.store'), $this->payload($scope))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.class-schedules.store'), $this->payload([
                'year' => $other['year'],
                'term' => $other['term'],
                'class' => $other['class'],
                'subject' => $other['subject'],
                'teacher' => $scope['teacher'],
            ], [
                'starts_at' => '07:30',
                'ends_at' => '09:00',
                'room' => 'R. 201',
            ]))
            ->assertSessionHasErrors('teacher_id');

        $this->assertSame(1, ClassSchedule::query()->count());
    }

    public function test_a_room_cannot_be_double_booked(): void
    {
        $admin = $this->admin();
        $scope = $this->academicScope();
        $other = $this->academicScope();
        $otherClass = ClassModel::factory()->create([
            'academic_year_id' => $other['year']->getKey(),
            'grade_level' => 8,
            'name' => 'B',
        ]);
        TeachingAssignment::factory()->create([
            'teacher_id' => $other['teacher']->getKey(),
            'class_id' => $otherClass->getKey(),
            'subject_id' => $other['subject']->getKey(),
            'academic_year_id' => $other['year']->getKey(),
            'term_id' => $other['term']->getKey(),
            'status' => AssignmentStatus::Active,
        ]);

        $this->actingAs($admin)->post(route('admin.class-schedules.store'), $this->payload($scope))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.class-schedules.store'), $this->payload([
                'year' => $other['year'],
                'term' => $other['term'],
                'class' => $otherClass,
                'subject' => $other['subject'],
                'teacher' => $other['teacher'],
            ], ['room' => 'R. 101']))
            ->assertSessionHasErrors('room');
    }

    public function test_a_roomless_lesson_never_conflicts_on_room(): void
    {
        $admin = $this->admin();
        $scope = $this->academicScope();
        $other = $this->academicScope();

        $this->actingAs($admin)->post(route('admin.class-schedules.store'), $this->payload($scope, ['room' => null]))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.class-schedules.store'), $this->payload([
                'year' => $other['year'],
                'term' => $other['term'],
                'class' => $other['class'],
                'subject' => $other['subject'],
                'teacher' => $other['teacher'],
            ], [
                'room' => null,
                'day_of_week' => WeekDay::Monday->value,
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_a_semester_from_another_academic_year_is_refused(): void
    {
        $admin = $this->admin();
        $scope = $this->academicScope();
        $foreignTerm = Term::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.class-schedules.store'), $this->payload($scope, ['term_id' => $foreignTerm->getKey()]))
            ->assertSessionHasErrors('term_id');

        $this->assertSame(0, ClassSchedule::query()->count());
    }

    public function test_a_teacher_without_an_assignment_cannot_be_timetabled(): void
    {
        $admin = $this->admin();
        $scope = $this->academicScope();
        $stranger = Teacher::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.class-schedules.store'), $this->payload($scope, ['teacher_id' => $stranger->getKey()]))
            ->assertSessionHasErrors('teacher_id');

        $this->assertSame(0, ClassSchedule::query()->count());
    }

    public function test_the_end_time_must_be_after_the_start_time(): void
    {
        $admin = $this->admin();
        $scope = $this->academicScope();

        $this->actingAs($admin)
            ->post(route('admin.class-schedules.store'), $this->payload($scope, [
                'starts_at' => '09:00',
                'ends_at' => '08:00',
            ]))
            ->assertSessionHasErrors('ends_at');
    }

    public function test_a_day_outside_the_school_week_is_refused(): void
    {
        $admin = $this->admin();
        $scope = $this->academicScope();

        // Sunday is not in the default allowed set.
        $this->actingAs($admin)
            ->post(route('admin.class-schedules.store'), $this->payload($scope, [
                'day_of_week' => WeekDay::Sunday->value,
            ]))
            ->assertSessionHasErrors('day_of_week');

        config()->set('sekolah-kita.schedule.allowed_days', [1, 2, 3, 4, 5, 6, 7]);

        $this->actingAs($admin)
            ->post(route('admin.class-schedules.store'), $this->payload($scope, [
                'day_of_week' => WeekDay::Sunday->value,
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_an_administrator_updates_deactivates_and_reactivates_a_lesson(): void
    {
        $admin = $this->admin();
        $scope = $this->academicScope();
        $this->actingAs($admin)->post(route('admin.class-schedules.store'), $this->payload($scope));
        $schedule = ClassSchedule::query()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.class-schedules.update', $schedule), $this->payload($scope, [
                'room' => 'R. 999',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('R. 999', $schedule->refresh()->room);

        $this->actingAs($admin)->post(route('admin.class-schedules.deactivate', $schedule))->assertRedirect();
        $this->assertFalse($schedule->refresh()->isActive());

        // A deactivated lesson stops blocking its slot, and can come back.
        $this->actingAs($admin)->post(route('admin.class-schedules.reactivate', $schedule))->assertRedirect();
        $this->assertTrue($schedule->refresh()->isActive());
    }

    public function test_a_deactivated_lesson_does_not_block_the_slot(): void
    {
        $admin = $this->admin();
        $scope = $this->academicScope();
        $this->actingAs($admin)->post(route('admin.class-schedules.store'), $this->payload($scope));
        $schedule = ClassSchedule::query()->firstOrFail();
        $this->actingAs($admin)->post(route('admin.class-schedules.deactivate', $schedule));

        $this->actingAs($admin)
            ->post(route('admin.class-schedules.store'), $this->payload($scope, ['room' => 'R. 202']))
            ->assertSessionHasNoErrors();
    }

    public function test_the_list_can_be_filtered(): void
    {
        $admin = $this->admin();
        $scope = $this->academicScope();
        $this->actingAs($admin)->post(route('admin.class-schedules.store'), $this->payload($scope));
        $this->actingAs($admin)->post(route('admin.class-schedules.store'), $this->payload($scope, [
            'day_of_week' => WeekDay::Wednesday->value,
            'starts_at' => '10:00',
            'ends_at' => '11:30',
            'room' => 'R. 303',
        ]));

        $this->actingAs($admin)
            ->get(route('admin.class-schedules.index', ['day_of_week' => WeekDay::Wednesday->value]))
            ->assertOk()
            ->assertSee('R. 303')
            ->assertDontSee('R. 101');

        $this->actingAs($admin)
            ->get(route('admin.class-schedules.index', ['teacher_id' => $scope['teacher']->getKey()]))
            ->assertOk()
            ->assertSee('R. 101');
    }

    /*
    |--------------------------------------------------------------------------
    | Teacher view
    |--------------------------------------------------------------------------
    */

    public function test_a_teacher_sees_only_their_own_timetable(): void
    {
        $scope = $this->academicScope();
        $mine = $this->teacherUser($scope['teacher']);

        $otherScope = $this->academicScope();
        $theirTeacher = $this->teacherUser($otherScope['teacher']);

        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.class-schedules.store'), $this->payload($scope))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.class-schedules.store'), $this->payload($otherScope, [
            'day_of_week' => WeekDay::Tuesday->value,
            'room' => 'R. 404',
        ]))->assertSessionHasNoErrors();

        $response = $this->actingAs($mine)->get(route('guru.jadwal.index'));

        $response->assertOk()
            ->assertSee('R. 101')
            ->assertDontSee('R. 404')
            ->assertSee('Matematika')
            ->assertSee('Jadwal pelajaran saya');
    }

    public function test_a_teacher_without_a_timetable_gets_a_useful_empty_state(): void
    {
        $scope = $this->academicScope();
        $user = $this->teacherUser($scope['teacher']);

        $this->actingAs($user)->get(route('guru.jadwal.index'))
            ->assertOk()
            ->assertSee('Belum ada jadwal')
            ->assertSee('Lihat kelas saya');
    }

    public function test_a_student_and_a_teacher_cannot_open_the_schedule_administration(): void
    {
        $scope = $this->academicScope();
        $teacherUser = $this->teacherUser($scope['teacher']);
        $studentUser = $this->studentUser($scope);

        $this->actingAs($teacherUser)->get(route('admin.class-schedules.index'))->assertForbidden();
        $this->actingAs($studentUser)->get(route('admin.class-schedules.index'))->assertForbidden();
        $this->actingAs($teacherUser)->post(route('admin.class-schedules.store'), $this->payload($scope))->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Student view
    |--------------------------------------------------------------------------
    */

    public function test_a_student_sees_only_their_own_class_timetable(): void
    {
        $scope = $this->academicScope();
        $studentUser = $this->studentUser($scope);

        $otherScope = $this->academicScope();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.class-schedules.store'), $this->payload($scope))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.class-schedules.store'), $this->payload($otherScope, [
            'day_of_week' => WeekDay::Tuesday->value,
            'room' => 'R. 505',
        ]))->assertSessionHasNoErrors();

        $response = $this->actingAs($studentUser)->get(route('siswa.jadwal.index'));

        $response->assertOk()
            ->assertSee('R. 101')
            ->assertDontSee('R. 505');
    }

    public function test_a_student_without_an_enrollment_is_told_so(): void
    {
        $scope = $this->academicScope();
        $user = User::factory()->create();
        $student = Student::factory()->create();
        $student->forceFill(['user_id' => $user->getKey()])->save();
        $user->forceFill(['role' => UserRole::Siswa])->save();

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.class-schedules.store'), $this->payload($scope));

        $this->actingAs($user->refresh())->get(route('siswa.jadwal.index'))
            ->assertOk()
            ->assertSee('Belum ada kelas');
    }

    /**
     * @param  array{year: AcademicYear, class: ClassModel}  $scope
     */
    private function studentUser(array $scope): User
    {
        $student = Student::factory()->create();
        Enrollment::factory()->active()->create([
            'student_id' => $student->getKey(),
            'class_id' => $scope['class']->getKey(),
            'academic_year_id' => $scope['year']->getKey(),
        ]);

        $user = User::factory()->create();
        $student->forceFill(['user_id' => $user->getKey()])->save();
        $user->forceFill(['role' => UserRole::Siswa])->save();

        return $user->refresh();
    }
}
