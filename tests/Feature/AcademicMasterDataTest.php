<?php

namespace Tests\Feature;

use App\Enums\AcademicYearStatus;
use App\Enums\AssignmentStatus;
use App\Enums\ClassStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\TermStatus;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\ClassTeacherAssignment;
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
 * The academic master data chain: academic year, term, class, subject, teaching
 * assignment, class teacher assignment and enrollment.
 *
 * Each case asserts the record really exists after the write, so a screen that
 * only appears to work cannot pass.
 */
class AcademicMasterDataTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    public function test_administrator_creates_an_active_academic_year(): void
    {
        $this->actingAs($this->admin())->post(route('admin.academic-years.store'), [
            'code' => '2026/2027',
            'label' => 'Tahun Ajaran 2026/2027',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'status' => AcademicYearStatus::Active->value,
        ])->assertRedirect(route('admin.academic-years.index'))->assertSessionHasNoErrors();

        $year = AcademicYear::query()->where('code', '2026/2027')->firstOrFail();

        $this->assertTrue($year->isActive());
        $this->assertSame('Tahun Ajaran 2026/2027', $year->label);

        $this->actingAs($this->admin())->get(route('admin.academic-years.index'))
            ->assertOk()
            ->assertSee('2026/2027');

        $this->actingAs($this->admin())->get(route('admin.academic-years.show', $year))
            ->assertOk()
            ->assertSee('2026/2027');
    }

    public function test_the_academic_year_code_format_and_date_order_are_validated(): void
    {
        $this->actingAs($this->admin())->post(route('admin.academic-years.store'), [
            'code' => 'DuaPuluhEnam',
            'label' => 'Salah',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'status' => AcademicYearStatus::Draft->value,
        ])->assertSessionHasErrors('code');

        $this->actingAs($this->admin())->post(route('admin.academic-years.store'), [
            'code' => '2026/2027',
            'label' => 'Salah',
            'starts_on' => '2027-06-30',
            'ends_on' => '2026-07-01',
            'status' => AcademicYearStatus::Draft->value,
        ])->assertSessionHasErrors('ends_on');
    }

    public function test_activating_a_second_academic_year_closes_the_first(): void
    {
        $admin = $this->admin();
        $first = AcademicYear::factory()->active()->create();
        $second = AcademicYear::factory()->draft()->create();

        $this->actingAs($admin)
            ->patch(route('admin.academic-years.status.update', $second), [
                'status' => AcademicYearStatus::Active->value,
            ])
            ->assertRedirect();

        $this->assertSame(AcademicYearStatus::Closed, $first->refresh()->status);
        $this->assertSame(AcademicYearStatus::Active, $second->refresh()->status);
    }

    public function test_administrator_creates_a_term_inside_an_academic_year(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->active()->create();

        $this->actingAs($admin)->post(route('admin.terms.store'), [
            'academic_year_id' => $year->id,
            'sequence' => 2,
            'label' => 'Semester 2',
            'starts_on' => '2027-01-01',
            'ends_on' => '2027-06-30',
            'status' => TermStatus::Active->value,
        ])->assertRedirect(route('admin.terms.index'))->assertSessionHasNoErrors();

        $term = Term::query()->where('academic_year_id', $year->id)->where('sequence', 2)->firstOrFail();

        $this->assertTrue($term->isActive());
        $this->actingAs($admin)->get(route('admin.terms.index'))->assertOk()->assertSee('Semester 2');
    }

    public function test_a_duplicate_term_sequence_in_the_same_year_is_rejected_before_the_insert(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->active()->create();
        Term::factory()->create(['academic_year_id' => $year->id, 'sequence' => 1]);

        $this->actingAs($admin)->post(route('admin.terms.store'), [
            'academic_year_id' => $year->id,
            'sequence' => 1,
            'label' => 'Duplikat',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-12-31',
            'status' => TermStatus::Draft->value,
        ])->assertSessionHasErrors('sequence');

        $this->assertSame(1, Term::query()->where('academic_year_id', $year->id)->count());
    }

    public function test_activating_a_term_closes_the_other_terms_of_the_same_year(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->active()->create();
        $first = Term::factory()->create(['academic_year_id' => $year->id, 'sequence' => 1, 'status' => TermStatus::Active]);
        $second = Term::factory()->create(['academic_year_id' => $year->id, 'sequence' => 2, 'status' => TermStatus::Draft]);

        $this->actingAs($admin)
            ->patch(route('admin.terms.status.update', $second), ['status' => TermStatus::Active->value])
            ->assertRedirect();

        $this->assertSame(TermStatus::Closed, $first->refresh()->status);
        $this->assertSame(TermStatus::Active, $second->refresh()->status);
    }

    public function test_administrator_creates_a_class_a_subject_and_the_teaching_assignment(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->active()->create();
        $term = Term::factory()->create(['academic_year_id' => $year->id, 'status' => TermStatus::Active]);
        $teacher = Teacher::factory()->create();
        $subject = Subject::factory()->create(['code' => 'FIS', 'name' => 'Fisika']);

        $this->actingAs($admin)->post(route('admin.classes.store'), [
            'academic_year_id' => $year->id,
            'grade_level' => 7,
            'name' => 'A',
            'capacity' => 30,
            'status' => ClassStatus::Active->value,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $class = ClassModel::query()->where('academic_year_id', $year->id)->where('grade_level', 7)->where('name', 'A')->firstOrFail();
        $this->assertSame('7 A', $class->getFullName());

        $this->actingAs($admin)->post(route('admin.subjects.store'), [
            'code' => 'MUS',
            'name' => 'Musik',
            'status' => 'active',
        ])->assertRedirect(route('admin.subjects.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('subjects', ['code' => 'MUS', 'name' => 'Musik']);
        $subject = Subject::query()->where('code', 'MUS')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.teaching-assignments.store'), [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $year->id,
            'term_id' => $term->id,
            'status' => AssignmentStatus::Active->value,
            'starts_on' => '2026-07-01',
        ])->assertRedirect(route('admin.teaching-assignments.index'))->assertSessionHasNoErrors();

        $assignment = TeachingAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->firstOrFail();

        $this->assertTrue($assignment->isActive());

        $this->actingAs($admin)->get(route('admin.teaching-assignments.index'))
            ->assertOk()
            ->assertSee($teacher->getDisplayName())
            ->assertSee($subject->name);

        $this->actingAs($admin)->get(route('admin.classes.show', $class))
            ->assertOk()
            ->assertSee($subject->name);
    }

    public function test_a_duplicate_active_teaching_assignment_is_rejected(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->active()->create();
        $class = ClassModel::factory()->create(['academic_year_id' => $year->id, 'status' => ClassStatus::Active]);
        $teacher = Teacher::factory()->create();
        $subject = Subject::factory()->create();

        TeachingAssignment::factory()->create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $year->id,
            'status' => AssignmentStatus::Active,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.teaching-assignments.create'))
            ->post(route('admin.teaching-assignments.store'), [
                'teacher_id' => $teacher->id,
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'academic_year_id' => $year->id,
                'status' => AssignmentStatus::Active->value,
                'starts_on' => '2026-07-01',
            ])
            ->assertSessionHasErrors('subject_id');

        $this->assertSame(1, TeachingAssignment::query()->where('teacher_id', $teacher->id)->count());
    }

    public function test_a_teaching_assignment_cannot_cross_academic_years(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->active()->create();
        $otherYear = AcademicYear::factory()->draft()->create();
        $class = ClassModel::factory()->create(['academic_year_id' => $year->id, 'status' => ClassStatus::Active]);

        $this->actingAs($admin)
            ->from(route('admin.teaching-assignments.create'))
            ->post(route('admin.teaching-assignments.store'), [
                'teacher_id' => Teacher::factory()->create()->id,
                'class_id' => $class->id,
                'subject_id' => Subject::factory()->create()->id,
                'academic_year_id' => $otherYear->id,
                'status' => AssignmentStatus::Active->value,
                'starts_on' => '2026-07-01',
            ])
            ->assertSessionHasErrors('academic_year_id');
    }

    public function test_administrator_assigns_a_class_teacher(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->active()->create();
        $class = ClassModel::factory()->create(['academic_year_id' => $year->id, 'status' => ClassStatus::Active]);
        $teacher = Teacher::factory()->create();

        $this->actingAs($admin)->post(route('admin.class-teacher-assignments.store'), [
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'status' => AssignmentStatus::Active->value,
            'starts_on' => '2026-07-01',
        ])->assertRedirect(route('admin.class-teacher-assignments.index'))->assertSessionHasNoErrors();

        $assignment = ClassTeacherAssignment::query()
            ->where('class_id', $class->id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $this->assertTrue($assignment->isActive());
        $this->actingAs($admin)->get(route('admin.classes.show', $class))->assertOk()->assertSee($teacher->getDisplayName());
    }

    public function test_a_class_cannot_have_two_active_homeroom_assignments_for_the_same_teacher(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->active()->create();
        $class = ClassModel::factory()->create(['academic_year_id' => $year->id, 'status' => ClassStatus::Active]);
        $teacher = Teacher::factory()->create();

        ClassTeacherAssignment::factory()->create([
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'status' => AssignmentStatus::Active,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.class-teacher-assignments.create'))
            ->post(route('admin.class-teacher-assignments.store'), [
                'class_id' => $class->id,
                'teacher_id' => $teacher->id,
                'status' => AssignmentStatus::Active->value,
                'starts_on' => '2026-07-01',
            ])
            ->assertSessionHasErrors('teacher_id');
    }

    public function test_administrator_enrolls_a_student_and_the_enrollment_appears_in_the_list(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->active()->create();
        $class = ClassModel::factory()->create(['academic_year_id' => $year->id, 'status' => ClassStatus::Active, 'capacity' => 2]);
        $student = Student::factory()->create();

        $this->actingAs($admin)->post(route('admin.enrollments.store'), [
            'student_id' => $student->id,
            'class_id' => $class->id,
            'enrolled_on' => '2026-07-01',
            'effective_from' => '2026-07-01',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $enrollment = Enrollment::query()->where('student_id', $student->id)->firstOrFail();

        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertSame($year->id, $enrollment->academic_year_id);
        $this->assertTrue($student->currentClass()?->is($class));

        $this->actingAs($admin)->get(route('admin.enrollments.index'))
            ->assertOk()
            ->assertSee($student->getDisplayName());

        $this->actingAs($admin)->get(route('admin.enrollments.show', $enrollment))
            ->assertOk()
            ->assertSee($student->getDisplayName())
            ->assertSee($class->getFullName());
    }

    public function test_a_second_active_enrollment_for_the_same_year_is_rejected(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->active()->create();
        $class = ClassModel::factory()->create(['academic_year_id' => $year->id, 'status' => ClassStatus::Active]);
        $student = Student::factory()->create();

        Enrollment::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year_id' => $year->id,
            'status' => EnrollmentStatus::Active->value,
            'enrolled_on' => '2026-07-01',
            'effective_from' => '2026-07-01',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.enrollments.create'))
            ->post(route('admin.enrollments.store'), [
                'student_id' => $student->id,
                'class_id' => $class->id,
                'enrolled_on' => '2026-07-01',
            ])
            ->assertSessionHasErrors('student_id');
    }

    public function test_class_capacity_cannot_be_lowered_below_the_existing_enrollment(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->active()->create();
        $class = ClassModel::factory()->create(['academic_year_id' => $year->id, 'capacity' => 5, 'status' => ClassStatus::Active]);
        $student = Student::factory()->create();

        Enrollment::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year_id' => $year->id,
            'status' => EnrollmentStatus::Active->value,
            'enrolled_on' => '2026-07-01',
            'effective_from' => '2026-07-01',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.classes.edit', $class))
            ->put(route('admin.classes.update', $class), [
                'academic_year_id' => $year->id,
                'grade_level' => $class->grade_level,
                'name' => $class->name,
                'capacity' => 0,
                'status' => ClassStatus::Active->value,
            ])
            ->assertSessionHasErrors('capacity');
    }

    public function test_an_enrollment_can_be_transferred_within_the_same_year(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->active()->create();
        $from = ClassModel::factory()->create(['academic_year_id' => $year->id, 'status' => ClassStatus::Active, 'name' => 'A']);
        $to = ClassModel::factory()->create(['academic_year_id' => $year->id, 'status' => ClassStatus::Active, 'name' => 'B']);
        $student = Student::factory()->create();

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'class_id' => $from->id,
            'academic_year_id' => $year->id,
            'status' => EnrollmentStatus::Active->value,
            'enrolled_on' => '2026-07-01',
            'effective_from' => '2026-07-01',
        ]);

        $this->actingAs($admin)->post(route('admin.enrollments.transfer', $enrollment), [
            'class_id' => $to->id,
            'effective_on' => '2026-08-01',
            'transition_reason' => 'Pindah]',
        ])->assertRedirect();

        $enrollment->refresh();
        $this->assertSame(EnrollmentStatus::Transferred, $enrollment->status);
        $this->assertSame('2026-08-01', $enrollment->effective_to->toDateString());
        $this->assertTrue($student->currentClass()?->is($to));
    }
}
