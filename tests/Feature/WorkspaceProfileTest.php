<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\ClassStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\TeacherEmploymentStatus;
use App\Enums\UserRole;
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
 * The teacher and student self-service profile screens.
 *
 * Both screens split the record three ways, because the three parts have
 * different owners: the account, the officially recorded identity, and the
 * academic assignments. The security property under test is that self-service
 * is narrow — a teacher may not promote themselves, change their NIP, or rewrite
 * their employment status, and a student may not rewrite their NIS or NISN.
 */
class WorkspaceProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Teacher profile
    |--------------------------------------------------------------------------
    */

    public function test_a_teacher_sees_their_own_biodata_split_into_three_sections(): void
    {
        $user = User::factory()->guru()->create(['name' => 'Guru Akun']);
        $teacher = Teacher::factory()->create([
            'legal_name' => 'Guru Resmi',
            'employee_number' => 'NIP-0001',
            'national_id' => '3201234567890123',
            'position_title' => 'Guru Matematika',
            'phone' => '081200000001',
            'address' => 'Jl. Guru No. 1',
        ]);
        $teacher->forceFill(['user_id' => $user->getKey()])->save();

        $this->actingAs($user->refresh())
            ->get(route('guru.profil'))
            ->assertOk()
            ->assertSee('Informasi akun')
            ->assertSee('Data resmi guru')
            ->assertSee('Kontak saya')
            ->assertSee('NIP-0001')
            ->assertSee('3201234567890123')
            ->assertSee('Guru Matematika')
            ->assertSee('Guru Resmi')
            ->assertSee('081200000001')
            // The account email is visible but not editable here.
            ->assertSee($user->email);
    }

    public function test_a_teacher_sees_their_teaching_assignments(): void
    {
        $user = User::factory()->guru()->create();
        $teacher = Teacher::factory()->create(['legal_name' => 'Guru']);
        $teacher->forceFill(['user_id' => $user->getKey()])->save();

        $year = AcademicYear::factory()->active()->create();
        $term = Term::factory()->create(['academic_year_id' => $year->getKey()]);
        $class = ClassModel::factory()->create(['academic_year_id' => $year->getKey(), 'status' => ClassStatus::Active]);
        $subject = Subject::factory()->create(['name' => 'Ilmu Pengetahuan Alam']);

        TeachingAssignment::factory()->create([
            'teacher_id' => $teacher->getKey(),
            'class_id' => $class->getKey(),
            'subject_id' => $subject->getKey(),
            'academic_year_id' => $year->getKey(),
            'term_id' => $term->getKey(),
            'status' => AssignmentStatus::Active,
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => null,
        ]);

        ClassTeacherAssignment::factory()->create([
            'class_id' => $class->getKey(),
            'teacher_id' => $teacher->getKey(),
            'status' => AssignmentStatus::Active,
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => null,
        ]);

        $this->actingAs($user->refresh())
            ->get(route('guru.profil'))
            ->assertOk()
            ->assertSee('Assignment mengajar')
            ->assertSee('Ilmu Pengetahuan Alam')
            ->assertSee('Wali kelas')
            ->assertSee($class->getFullName());
    }

    public function test_a_teacher_may_update_only_their_own_contact_fields(): void
    {
        $user = User::factory()->guru()->create();
        $teacher = Teacher::factory()->create(['phone' => '081200000001', 'address' => 'Alamat lama']);
        $teacher->forceFill(['user_id' => $user->getKey()])->save();

        $this->actingAs($user->refresh())
            ->put(route('guru.profil.update'), [
                'phone' => '081299999999',
                'address' => 'Alamat baru',
                // Mass assignment attempt: none of these are in the rules, and the
                // ones that are on the model are guarded by UpdateTeacherProfileRequest.
                'employee_number' => 'NIP-HACK',
                'employment_status' => TeacherEmploymentStatus::Retired->value,
                'position_title' => 'Kepala Sekolah',
                'is_publicly_visible' => '1',
            ])
            ->assertRedirect(route('guru.profil'))
            ->assertSessionHasNoErrors();

        $teacher->refresh();

        $this->assertSame('081299999999', $teacher->phone);
        $this->assertSame('Alamat baru', $teacher->address);
        $this->assertNotSame('NIP-HACK', $teacher->employee_number);
        $this->assertSame(TeacherEmploymentStatus::Active, $teacher->employment_status);
        $this->assertNotSame('Kepala Sekolah', $teacher->position_title);
        $this->assertFalse((bool) $teacher->is_publicly_visible);
    }

    public function test_the_contact_fields_are_validated(): void
    {
        $user = User::factory()->guru()->create();
        $teacher = Teacher::factory()->create();
        $teacher->forceFill(['user_id' => $user->getKey()])->save();

        $this->actingAs($user->refresh())
            ->put(route('guru.profil.update'), ['phone' => 'bukan-nomor'])
            ->assertSessionHasErrors('phone');
    }

    public function test_a_teacher_without_a_teacher_record_is_redirected_not_shown_a_blank_page(): void
    {
        $user = User::factory()->guru()->create();

        $this->actingAs($user)
            ->get(route('guru.profil'))
            ->assertRedirect(route('dashboard.guru'))
            ->assertSessionHas('status');
    }

    public function test_a_teacher_cannot_open_another_teachers_profile(): void
    {
        $user = User::factory()->guru()->create();
        $teacher = Teacher::factory()->create();
        $teacher->forceFill(['user_id' => $user->getKey()])->save();

        $otherTeacher = Teacher::factory()->create();
        $otherUser = User::factory()->guru()->create();
        $otherTeacher->forceFill(['user_id' => $otherUser->getKey()])->save();

        $this->assertFalse($user->can('update', $otherTeacher));
        $this->assertTrue($user->can('update', $teacher->refresh()));
    }

    public function test_a_student_cannot_open_the_teacher_profile(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::Siswa])->save();

        $this->actingAs($user->refresh())->get(route('guru.profil'))->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Student profile
    |--------------------------------------------------------------------------
    */

    private function studentScenario(): array
    {
        $user = User::factory()->create(['name' => 'Siswa Akun']);
        $student = Student::factory()->create([
            'legal_name' => 'Siswa Resmi',
            'preferred_name' => null,
            'student_number' => 'NIS-0001',
            'nisn' => '1234567890',
            'phone' => '081200000009',
            'address' => 'Alamat lama',
        ]);
        $student->forceFill(['user_id' => $user->getKey()])->save();
        $user->forceFill(['role' => UserRole::Siswa])->save();

        $year = AcademicYear::factory()->active()->create();
        $class = ClassModel::factory()->create([
            'academic_year_id' => $year->getKey(),
            'grade_level' => 7,
            'name' => 'B',
            'status' => ClassStatus::Active,
        ]);
        Enrollment::factory()->active()->create([
            'student_id' => $student->getKey(),
            'class_id' => $class->getKey(),
            'academic_year_id' => $year->getKey(),
            'status' => EnrollmentStatus::Active,
        ]);

        return compact('user', 'student', 'year', 'class');
    }

    public function test_a_student_sees_their_own_biodata_and_current_class(): void
    {
        $scope = $this->studentScenario();

        $this->actingAs($scope['user']->refresh())
            ->get(route('siswa.profil'))
            ->assertOk()
            ->assertSee('Informasi akun')
            ->assertSee('Data resmi siswa')
            ->assertSee('Kontak saya')
            ->assertSee('NIS-0001')
            ->assertSee('1234567890')
            ->assertSee('Siswa Resmi')
            ->assertSee($scope['class']->getFullName())
            ->assertSee('Enrollment berjalan');
    }

    public function test_a_student_may_update_only_their_own_contact_fields(): void
    {
        $scope = $this->studentScenario();

        $this->actingAs($scope['user']->refresh())
            ->put(route('siswa.profil.update'), [
                'phone' => '081288888888',
                'address' => 'Alamat baru',
                'student_number' => 'NIS-HACK',
                'nisn' => '9999999999',
                'national_id' => '3201111111111111',
                'birth_date' => '2000-01-01',
            ])
            ->assertRedirect(route('siswa.profil'))
            ->assertSessionHasNoErrors();

        $student = $scope['student']->refresh();

        $this->assertSame('081288888888', $student->phone);
        $this->assertSame('Alamat baru', $student->address);
        $this->assertSame('NIS-0001', $student->student_number);
        $this->assertSame('1234567890', $student->nisn);
        $this->assertNotSame('3201111111111111', $student->national_id);
        $this->assertNotSame('2000-01-01', $student->birth_date?->toDateString());
    }

    public function test_the_student_contact_fields_are_validated(): void
    {
        $scope = $this->studentScenario();

        $this->actingAs($scope['user']->refresh())
            ->put(route('siswa.profil.update'), ['phone' => 'abc'])
            ->assertSessionHasErrors('phone');
    }

    public function test_a_student_without_a_student_record_is_redirected(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::Siswa])->save();

        $this->actingAs($user->refresh())
            ->get(route('siswa.profil'))
            ->assertRedirect(route('dashboard.siswa'))
            ->assertSessionHas('status');
    }

    public function test_a_student_cannot_read_another_students_record_by_changing_a_url(): void
    {
        $scope = $this->studentScenario();

        $other = Student::factory()->create(['legal_name' => 'Siswa Lain', 'preferred_name' => null]);
        $otherUser = User::factory()->create();
        $other->forceFill(['user_id' => $otherUser->getKey()])->save();
        $otherUser->forceFill(['role' => UserRole::Siswa])->save();

        // Neither profile URL carries a student id at all, and the record each
        // account reaches is resolved from its own user row.
        $this->actingAs($scope['user']->refresh())
            ->get(route('siswa.profil'))
            ->assertOk()
            ->assertSee('Siswa Resmi')
            ->assertDontSee('NIS-HACK');

        $this->assertFalse(
            $scope['user']->can('view', $other),
            'A student cannot view another student record even through the policy.'
        );
        $this->assertTrue($scope['user']->can('view', $scope['student']->refresh()));
    }

    public function test_a_teacher_cannot_open_the_student_self_service_profile(): void
    {
        $user = User::factory()->guru()->create();
        $teacher = Teacher::factory()->create();
        $teacher->forceFill(['user_id' => $user->getKey()])->save();

        $this->actingAs($user->refresh())->get(route('siswa.profil'))->assertForbidden();
    }

    public function test_a_guest_cannot_reach_either_profile(): void
    {
        $this->get(route('guru.profil'))->assertRedirect(route('login'));
        $this->get(route('siswa.profil'))->assertRedirect(route('login'));
    }
}
