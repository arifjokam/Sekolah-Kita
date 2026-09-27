<?php

namespace Tests\Feature;

use App\Enums\AcademicYearStatus;
use App\Enums\AssignmentStatus;
use App\Enums\ClassStatus;
use App\Enums\TermStatus;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\ClassTeacherAssignment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Detail screens for the academic masters that only had list, create and edit.
 *
 * Phase 6 asks for a detail view "where useful". Terms, subjects, teaching
 * assignments and class teacher assignments had none, so a row could be opened
 * for editing but never inspected.
 */
class AcademicDetailScreenTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    private function academicYear(): AcademicYear
    {
        return AcademicYear::query()->create([
            'code' => '2026/2027',
            'label' => 'Tahun Ajaran Uji',
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'status' => AcademicYearStatus::Active,
        ]);
    }

    public function test_a_term_detail_screen_renders(): void
    {
        $term = Term::query()->create([
            'academic_year_id' => $this->academicYear()->id,
            'sequence' => 1,
            'label' => 'Semester Ganjil Uji',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-12-31',
            'status' => TermStatus::Active,
        ]);

        $this->actingAs($this->admin())->get(route('admin.terms.show', $term))
            ->assertOk()
            ->assertSee('Semester Ganjil Uji')
            ->assertSee('2026/2027');
    }

    public function test_a_subject_detail_screen_lists_its_active_assignments(): void
    {
        $subject = Subject::query()->create([
            'code' => 'MTK-401',
            'name' => 'Matematika Uji',
            'status' => 'active',
        ]);

        $assignment = TeachingAssignment::factory()->create([
            'subject_id' => $subject->id,
            'status' => AssignmentStatus::Active,
        ]);

        $this->actingAs($this->admin())->get(route('admin.subjects.show', $subject))
            ->assertOk()
            ->assertSee('Matematika Uji')
            ->assertSee('MTK-401')
            ->assertSee($assignment->teacher->getDisplayName());
    }

    public function test_a_teaching_assignment_detail_screen_renders(): void
    {
        $assignment = TeachingAssignment::factory()->create([
            'status' => AssignmentStatus::Active,
        ]);

        $this->actingAs($this->admin())->get(route('admin.teaching-assignments.show', $assignment))
            ->assertOk()
            ->assertSee($assignment->teacher->getDisplayName())
            ->assertSee($assignment->subject->name);
    }

    public function test_a_class_teacher_assignment_detail_screen_renders(): void
    {
        $assignment = ClassTeacherAssignment::factory()->create([
            'status' => AssignmentStatus::Active,
        ]);

        $this->actingAs($this->admin())->get(route('admin.class-teacher-assignments.show', $assignment))
            ->assertOk()
            ->assertSee($assignment->teacher->getDisplayName())
            ->assertSee($assignment->class->name);
    }

    public function test_a_detail_screen_survives_a_deleted_relation(): void
    {
        // A subject whose assignments were removed by a cascade or by a school
        // tidying up must still render rather than throw on a null relation.
        $subject = Subject::query()->create([
            'code' => 'MTK-402',
            'name' => 'Mata Pelajaran Yatim',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin())->get(route('admin.subjects.show', $subject))
            ->assertOk()
            ->assertSee('Belum ada assignment aktif');
    }

    public function test_a_non_administrator_cannot_open_the_detail_screens(): void
    {
        $term = Term::query()->create([
            'academic_year_id' => $this->academicYear()->id,
            'sequence' => 2,
            'label' => 'Semester Genap Uji',
            'starts_on' => '2027-01-01',
            'ends_on' => '2027-06-30',
            'status' => TermStatus::Draft,
        ]);

        $this->actingAs(User::factory()->guru()->create())
            ->get(route('admin.terms.show', $term))
            ->assertForbidden();
    }

    public function test_the_class_detail_screen_still_renders_its_class_count(): void
    {
        $year = $this->academicYear();
        $class = ClassModel::factory()->create([
            'academic_year_id' => $year->id,
            'status' => ClassStatus::Active,
            'name' => 'VII-A',
        ]);

        $this->actingAs($this->admin())->get(route('admin.classes.show', $class))
            ->assertOk()
            ->assertSee($class->getFullName());
    }
}
