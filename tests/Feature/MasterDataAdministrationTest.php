<?php

namespace Tests\Feature;

use App\Enums\ClassStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\StudentLifecycleStatus;
use App\Enums\TeacherEmploymentStatus;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\Teacher;
use App\Models\User;
use App\Services\StudentNumberAllocator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The administrator master data screens: create, list, search, filter, detail,
 * status transitions, validation and authorization.
 */
class MasterDataAdministrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    /*
    |--------------------------------------------------------------------------
    | Teachers
    |--------------------------------------------------------------------------
    */

    public function test_administrator_creates_a_teacher_and_sees_it_in_the_list(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.teachers.store'), [
            'legal_name' => 'Ratna Kumala',
            'preferred_name' => 'Ratna',
            'employee_number' => 'NIP-2026-0001',
            'employment_status' => TeacherEmploymentStatus::Active->value,
            'employment_start_date' => '2026-07-01',
            'position_title' => 'Guru Bahasa Indonesia',
            'email' => 'ratna@sekolah.local',
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $teacher = Teacher::query()->where('employee_number', 'NIP-2026-0001')->firstOrFail();

        $this->assertSame('Ratna Kumala', $teacher->legal_name);
        $this->assertNull($teacher->user_id, 'Creating a teacher must not silently create a login account.');

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'teacher.created',
            'target_type' => 'teacher',
            'target_id' => $teacher->id,
        ]);

        $this->actingAs($admin)->get(route('admin.teachers.index'))
            ->assertOk()
            ->assertSee('Ratna Kumala')
            ->assertSee('NIP-2026-0001');

        $this->actingAs($admin)->get(route('admin.teachers.show', $teacher))
            ->assertOk()
            ->assertSee('Ratna Kumala')
            ->assertSee('Belum ada penugasan mengajar aktif');
    }

    public function test_teacher_list_supports_search_and_status_filter(): void
    {
        $admin = $this->admin();
        $active = Teacher::factory()->create(['legal_name' => 'Sari Aktif', 'employment_status' => TeacherEmploymentStatus::Active]);
        Teacher::factory()->inactive()->create(['legal_name' => 'Budi Nonaktif']);

        $this->actingAs($admin)->get(route('admin.teachers.index', ['search' => 'Sari']))
            ->assertOk()
            ->assertSee('Sari Aktif')
            ->assertDontSee('Budi Nonaktif');

        $this->actingAs($admin)->get(route('admin.teachers.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Budi Nonaktif')
            ->assertDontSee('Sari Aktif');
    }

    public function test_teacher_creation_rejects_a_duplicate_employee_number_and_a_bad_date(): void
    {
        Teacher::factory()->create(['employee_number' => 'NIP-2026-0001']);

        $this->actingAs($this->admin())->post(route('admin.teachers.store'), [
            'legal_name' => 'Duplikat',
            'employee_number' => 'NIP-2026-0001',
            'employment_status' => TeacherEmploymentStatus::Active->value,
            'employment_start_date' => '2026-07-01',
        ])->assertSessionHasErrors('employee_number');

        $this->actingAs($this->admin())->post(route('admin.teachers.store'), [
            'legal_name' => 'Tanggal Salah',
            'employee_number' => 'NIP-2026-0002',
            'employment_status' => TeacherEmploymentStatus::Active->value,
            'employment_start_date' => '2026-07-01',
            'employment_end_date' => '2026-01-01',
        ])->assertSessionHasErrors('employment_end_date');
    }

    public function test_teacher_employment_status_change_reactivates_the_linked_account(): void
    {
        $admin = $this->admin();
        $user = User::factory()->guru()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->id, 'employment_status' => TeacherEmploymentStatus::Active]);

        $this->actingAs($admin)->patch(route('admin.teachers.employment-status.update', $teacher), [
            'employment_status' => TeacherEmploymentStatus::Inactive->value,
        ])->assertRedirect();

        $this->assertSame(TeacherEmploymentStatus::Inactive, $teacher->refresh()->employment_status);
        $this->assertFalse($user->refresh()->isActive());

        $this->actingAs($admin)->patch(route('admin.teachers.employment-status.update', $teacher), [
            'employment_status' => TeacherEmploymentStatus::Active->value,
        ])->assertRedirect();

        $this->assertTrue($user->refresh()->isActive());
    }

    public function test_a_teacher_cannot_be_activated_before_the_start_date(): void
    {
        $teacher = Teacher::factory()->create([
            'employment_status' => TeacherEmploymentStatus::Leave,
            'employment_start_date' => now()->addMonth()->toDateString(),
        ]);

        $this->actingAs($this->admin())
            ->from(route('admin.teachers.show', $teacher))
            ->patch(route('admin.teachers.employment-status.update', $teacher), [
                'employment_status' => TeacherEmploymentStatus::Active->value,
            ])
            ->assertSessionHasErrors('employment_status');
    }

    /*
    |--------------------------------------------------------------------------
    | Students
    |--------------------------------------------------------------------------
    */

    public function test_administrator_creates_a_student_with_a_guardian_and_an_enrollment(): void
    {
        $admin = $this->admin();
        $class = ClassModel::factory()->active()->create();

        $this->actingAs($admin)->post(route('admin.students.store'), [
            'legal_name' => 'Nadia Putri',
            'status' => StudentLifecycleStatus::Active->value,
            'admission_date' => '2026-07-01',
            'gender' => 'P',
            'has_guardian' => '1',
            'guardian' => [
                'legal_name' => 'Sari Putri',
                'relationship_type' => 'mother',
                'phone' => '0812-0000-0001',
                'is_primary_contact' => '1',
            ],
            'has_enrollment' => '1',
            'enrollment' => [
                'class_id' => $class->id,
                'enrolled_on' => '2026-07-01',
                'effective_from' => '2026-07-01',
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $student = Student::query()->where('legal_name', 'Nadia Putri')->firstOrFail();

        $this->assertNotNull($student->student_number, 'A student number must be allocated when the field is left empty.');
        $this->assertMatchesRegularExpression('/^SK-'.now()->format('Y').'-[A-Z0-9]{8}$/', $student->student_number);
        $this->assertNull($student->user_id, 'Creating a student must not silently create a login account.');

        $this->assertSame(1, $student->guardians()->count());
        $this->assertSame(1, $student->enrollments()->where('status', EnrollmentStatus::Active->value)->count());
        $this->assertTrue($student->currentEnrollment()->class->is($class));

        $guardianLink = StudentGuardian::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('mother', $guardianLink->relationship_type->value);
        $this->assertTrue($guardianLink->is_primary_contact);
        $this->assertInstanceOf(Guardian::class, $guardianLink->guardian);

        $this->actingAs($admin)->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('Nadia Putri');

        $this->actingAs($admin)->get(route('admin.students.show', $student))
            ->assertOk()
            ->assertSee('Nadia Putri')
            ->assertSee('Sari Putri');
    }

    public function test_student_list_supports_search_and_class_filter(): void
    {
        $admin = $this->admin();
        $class = ClassModel::factory()->active()->create();
        $inside = Student::factory()->create(['legal_name' => 'Dalam Kelas']);
        $outside = Student::factory()->create(['legal_name' => 'Luar Kelas']);

        Enrollment::query()->create([
            'student_id' => $inside->id,
            'class_id' => $class->id,
            'academic_year_id' => $class->academic_year_id,
            'status' => EnrollmentStatus::Active->value,
            'enrolled_on' => '2026-07-01',
            'effective_from' => '2026-07-01',
        ]);

        $this->actingAs($admin)->get(route('admin.students.index', ['class_id' => $class->id]))
            ->assertOk()
            ->assertSee('Dalam Kelas')
            ->assertDontSee('Luar Kelas');

        $this->actingAs($admin)->get(route('admin.students.index', ['search' => 'Luar']))
            ->assertOk()
            ->assertSee('Luar Kelas');
    }

    public function test_a_guardian_is_reused_when_the_phone_already_belongs_to_one(): void
    {
        $admin = $this->admin();
        $class = ClassModel::factory()->active()->create();

        foreach (['Satu', 'Dua'] as $index => $name) {
            $this->actingAs($admin)->post(route('admin.students.store'), [
                'legal_name' => 'Siswa '.$name,
                'status' => StudentLifecycleStatus::Active->value,
                'admission_date' => '2026-07-01',
                'has_guardian' => '1',
                'guardian' => [
                    'legal_name' => 'Wali Bersama',
                    'relationship_type' => 'father',
                    'phone' => '0812-0000-9999',
                ],
                'has_enrollment' => '1',
                'enrollment' => ['class_id' => $class->id, 'enrolled_on' => '2026-07-01'],
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(1, Guardian::query()->where('phone', '0812-0000-9999')->count());
        $this->assertSame(2, StudentGuardian::query()->count());
    }

    public function test_a_student_cannot_be_graduated_while_an_active_enrollment_exists(): void
    {
        $admin = $this->admin();
        $class = ClassModel::factory()->active()->create();
        $student = Student::factory()->create();

        Enrollment::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year_id' => $class->academic_year_id,
            'status' => EnrollmentStatus::Active->value,
            'enrolled_on' => '2026-07-01',
            'effective_from' => '2026-07-01',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.students.show', $student))
            ->patch(route('admin.students.lifecycle-status.update', $student), [
                'status' => StudentLifecycleStatus::Graduated->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(StudentLifecycleStatus::Active, $student->refresh()->status);
    }

    public function test_detaching_a_guardian_ends_the_relationship_instead_of_deleting_history(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create();
        $guardian = Guardian::factory()->create();

        $link = StudentGuardian::query()->create([
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'relationship_type' => 'father',
            'starts_on' => '2026-07-01',
            'ends_on' => null,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.students.guardians.destroy', [$student, $link]))
            ->assertRedirect();

        $this->assertDatabaseHas('student_guardians', ['id' => $link->id]);
        $this->assertNotNull($link->refresh()->ends_on);
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_only_administrators_reach_the_master_data_screens(): void
    {
        $admin = $this->admin();
        $guru = User::factory()->guru()->create();
        $siswa = User::factory()->create();
        $teacher = Teacher::factory()->create();
        $student = Student::factory()->create();

        $screens = [
            route('admin.teachers.index'),
            route('admin.teachers.create'),
            route('admin.teachers.show', $teacher),
            route('admin.teachers.edit', $teacher),
            route('admin.students.index'),
            route('admin.students.create'),
            route('admin.students.show', $student),
            route('admin.students.edit', $student),
            route('admin.academic-years.index'),
            route('admin.terms.index'),
            route('admin.classes.index'),
            route('admin.subjects.index'),
            route('admin.teaching-assignments.index'),
            route('admin.class-teacher-assignments.index'),
            route('admin.enrollments.index'),
            route('admin.ppdb.periods.index'),
            route('admin.ppdb.applications.index'),
            route('admin.ppdb.review.index'),
        ];

        foreach ($screens as $screen) {
            $this->actingAs($admin)->get($screen)->assertOk();
            $this->actingAs($guru)->get($screen)->assertForbidden();
            $this->actingAs($siswa)->get($screen)->assertForbidden();
        }
    }

    public function test_guests_are_redirected_to_login_from_every_master_data_screen(): void
    {
        $teacher = Teacher::factory()->create();
        $student = Student::factory()->create();

        $screens = [
            route('admin.teachers.index'),
            route('admin.teachers.create'),
            route('admin.teachers.show', $teacher),
            route('admin.students.index'),
            route('admin.students.show', $student),
            route('admin.academic-years.index'),
            route('admin.terms.index'),
            route('admin.classes.index'),
            route('admin.subjects.index'),
            route('admin.teaching-assignments.index'),
            route('admin.class-teacher-assignments.index'),
            route('admin.enrollments.index'),
            route('admin.ppdb.periods.index'),
            route('admin.ppdb.applications.index'),
            route('admin.ppdb.review.index'),
        ];

        foreach ($screens as $screen) {
            $this->get($screen)->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_only_administrators_reach_the_master_data_write_endpoints(): void
    {
        $guru = User::factory()->guru()->create();
        $teacher = Teacher::factory()->create();
        $student = Student::factory()->create();

        $this->actingAs($guru)->post(route('admin.teachers.store'), [])->assertForbidden();
        $this->actingAs($guru)->put(route('admin.teachers.update', $teacher), [])->assertForbidden();
        $this->actingAs($guru)->patch(route('admin.teachers.employment-status.update', $teacher), [])->assertForbidden();
        $this->actingAs($guru)->post(route('admin.students.store'), [])->assertForbidden();
        $this->actingAs($guru)->put(route('admin.students.update', $student), [])->assertForbidden();
        $this->actingAs($guru)->post(route('admin.students.guardians.store', $student), [])->assertForbidden();
        $this->actingAs($guru)->post(route('admin.academic-years.store'), [])->assertForbidden();
        $this->actingAs($guru)->post(route('admin.classes.store'), [])->assertForbidden();
        $this->actingAs($guru)->post(route('admin.subjects.store'), [])->assertForbidden();
        $this->actingAs($guru)->post(route('admin.enrollments.store'), [])->assertForbidden();
    }

    public function test_the_student_number_allocator_never_collides(): void
    {
        $first = app(StudentNumberAllocator::class)->allocate();
        $second = app(StudentNumberAllocator::class)->allocate();

        $this->assertNotSame($first, $second);
    }

    public function test_class_status_cannot_be_activated_outside_an_active_academic_year(): void
    {
        $class = ClassModel::factory()->planned()->create();

        $this->actingAs($this->admin())
            ->from(route('admin.classes.show', $class))
            ->patch(route('admin.classes.status.update', $class), [
                'status' => ClassStatus::Active->value,
            ])
            ->assertSessionHasErrors('status');
    }
}
