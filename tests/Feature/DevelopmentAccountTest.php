<?php

namespace Tests\Feature;

use App\Enums\AcademicYearStatus;
use App\Enums\AccountStatus;
use App\Enums\AdmissionPeriodStatus;
use App\Enums\AssignmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\PublicationStatus;
use App\Enums\StudentLifecycleStatus;
use App\Enums\TeacherEmploymentStatus;
use App\Enums\TermStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AdmissionApplication;
use App\Models\AdmissionPeriod;
use App\Models\ClassModel;
use App\Models\ClassTeacherAssignment;
use App\Models\Enrollment;
use App\Models\NewsArticle;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\DemoUatSeeder;
use Database\Seeders\DevelopmentAccountsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The three local development accounts must be reproducible, idempotent and
 * usable, and they must never be provisioned implicitly in production.
 */
class DevelopmentAccountTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return list<array{name: string, email: string, role: UserRole, route: string}>
     */
    public static function accountProvider(): array
    {
        return [
            ['Administrator Test', 'admin.test@sekolah.local', UserRole::Administrator, 'dashboard.administrator'],
            ['Guru Test', 'guru.test@sekolah.local', UserRole::Guru, 'dashboard.guru'],
            ['Siswa Test', 'siswa.test@sekolah.local', UserRole::Siswa, 'dashboard.siswa'],
        ];
    }

    #[DataProvider('accountProvider')]
    public function test_the_demo_command_provisions_an_active_verified_account(
        string $name,
        string $email,
        UserRole $role,
        string $landingRoute,
    ): void {
        $this->seed(DemoUatSeeder::class);

        $user = User::query()->where('email', $email)->firstOrFail();

        $this->assertSame($name, $user->name);
        $this->assertSame($role, $user->role);
        $this->assertTrue($user->isActive());
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check(DevelopmentAccountsSeeder::PASSWORD, $user->password));

        $this->post(route('login'), [
            'email' => strtoupper($email),
            'password' => DevelopmentAccountsSeeder::PASSWORD,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertRedirect(route($landingRoute));
    }

    public function test_the_demo_command_is_idempotent(): void
    {
        $this->artisan('sekolah:seed-demo --force')->assertSuccessful();
        $firstUserId = User::query()->where('email', 'admin.test@sekolah.local')->value('id');
        $firstTeacherId = Teacher::query()->where('employee_number', 'NIP-DEV-0001')->value('id');

        $this->artisan('sekolah:seed-demo --force')->assertSuccessful();

        $this->assertSame($firstUserId, User::query()->where('email', 'admin.test@sekolah.local')->value('id'));
        $this->assertSame($firstTeacherId, Teacher::query()->where('employee_number', 'NIP-DEV-0001')->value('id'));
        $this->assertSame(1, User::query()->where('email', 'admin.test@sekolah.local')->count());
        $this->assertTrue(Hash::check(DevelopmentAccountsSeeder::PASSWORD, User::query()->where('email', 'admin.test@sekolah.local')->value('password')));
    }

    public function test_the_demo_command_provisions_the_full_uat_dataset(): void
    {
        $this->artisan('sekolah:seed-demo --force')->assertSuccessful();

        $this->assertGreaterThan(0, AcademicYear::query()->where('status', AcademicYearStatus::Active->value)->count());
        $this->assertGreaterThan(0, Term::query()->where('status', TermStatus::Active->value)->count());
        $this->assertGreaterThanOrEqual(4, ClassModel::query()->count());
        $this->assertGreaterThanOrEqual(10, Subject::query()->count());
        $this->assertGreaterThanOrEqual(2, Teacher::query()->count());
        $this->assertGreaterThanOrEqual(3, Student::query()->count());
        $this->assertGreaterThanOrEqual(3, Enrollment::query()->where('status', EnrollmentStatus::Active->value)->count());
        $this->assertGreaterThan(0, StudentGuardian::query()->whereNull('ends_on')->count());
        $this->assertGreaterThan(0, TeachingAssignment::query()->where('status', AssignmentStatus::Active->value)->count());
        $this->assertGreaterThan(0, ClassTeacherAssignment::query()->where('status', AssignmentStatus::Active->value)->count());
        $this->assertGreaterThan(0, AdmissionPeriod::query()->where('status', AdmissionPeriodStatus::Open->value)->count());
        $this->assertGreaterThanOrEqual(2, AdmissionApplication::query()->count());
        $this->assertGreaterThan(0, NewsArticle::query()->where('status', PublicationStatus::Published->value)->count());
    }

    public function test_the_demo_command_refuses_to_run_in_production(): void
    {
        // The environment is resolved once per process, so the guard has to be
        // exercised by rebinding the container value the command reads.
        $this->app->instance('env', 'production');

        $this->artisan('sekolah:seed-demo --force')
            ->expectsOutputToContain('cannot be seeded in production')
            ->assertFailed();

        $this->assertSame(0, User::query()->where('email', 'admin.test@sekolah.local')->count());
    }

    public function test_the_guru_and_siswa_identities_are_linked_to_their_profiles(): void
    {
        $this->artisan('sekolah:seed-demo --force')->assertSuccessful();

        $guru = User::query()->where('email', 'guru.test@sekolah.local')->firstOrFail();
        $siswa = User::query()->where('email', 'siswa.test@sekolah.local')->firstOrFail();

        $this->assertInstanceOf(Teacher::class, $guru->teacher);
        $this->assertSame(TeacherEmploymentStatus::Active, $guru->teacher->employment_status);
        $this->assertInstanceOf(Student::class, $siswa->student);
        $this->assertSame(StudentLifecycleStatus::Active, $siswa->student->status);
    }

    public function test_the_demo_command_always_leaves_the_accounts_usable(): void
    {
        $this->artisan('sekolah:seed-demo --force')->assertSuccessful();

        // A suspended fixture account would break every authenticated screen, so
        // the seeder always leaves the three accounts active and verified.
        foreach (DevelopmentAccountsSeeder::ACCOUNTS as $account) {
            $user = User::query()->where('email', $account['email'])->firstOrFail();

            $this->assertSame(AccountStatus::Active, $user->status);
        }
    }
}
