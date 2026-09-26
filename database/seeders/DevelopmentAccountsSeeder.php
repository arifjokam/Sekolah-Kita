<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\StudentLifecycleStatus;
use App\Enums\TeacherEmploymentStatus;
use App\Enums\UserRole;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Development-only login identities for UAT.
 *
 * Production has no fixed credentials by design: accounts are provisioned
 * through the administrator flow, and AccountLifecycleService is the only
 * supported way to change a role or a status. Local development still needs a
 * repeatable way in, so this seeder mints exactly three known accounts and is
 * wired only to the development fixture command, which refuses to run in
 * production.
 */
class DevelopmentAccountsSeeder extends Seeder
{
    public const PASSWORD = 'SekolahKita#Dev2026';

    /**
     * @var list<array{name: string, email: string, role: UserRole}>
     */
    public const ACCOUNTS = [
        ['name' => 'Administrator Test', 'email' => 'admin.test@sekolah.local', 'role' => UserRole::Administrator],
        ['name' => 'Guru Test', 'email' => 'guru.test@sekolah.local', 'role' => UserRole::Guru],
        ['name' => 'Siswa Test', 'email' => 'siswa.test@sekolah.local', 'role' => UserRole::Siswa],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as $account) {
            $user = User::query()->firstOrNew(['email' => $account['email']]);

            $user->forceFill([
                'name' => $account['name'],
                'role' => $account['role'],
                'status' => AccountStatus::Active,
                'email_verified_at' => $user->email_verified_at ?? now(),
                'password' => Hash::make(self::PASSWORD),
            ])->save();
        }

        $this->linkGuru();
        $this->linkSiswa();
    }

    /**
     * The guru and siswa identities only mean something to a portal user when a
     * teacher and a student profile point back at them.
     */
    private function linkGuru(): void
    {
        $user = User::query()->where('email', 'guru.test@sekolah.local')->first();

        if (! $user instanceof User) {
            return;
        }

        $teacher = Teacher::query()->firstOrNew(['user_id' => $user->getKey()]);

        if (! $teacher->exists) {
            $teacher->forceFill([
                'legal_name' => $user->name,
                'employee_number' => 'NIP-DEV-0001',
                'employment_status' => TeacherEmploymentStatus::Active,
                'employment_start_date' => now()->subYears(2)->startOfYear()->toDateString(),
                'position_title' => 'Guru Matematika',
            ])->save();
        }
    }

    private function linkSiswa(): void
    {
        $user = User::query()->where('email', 'siswa.test@sekolah.local')->first();

        if (! $user instanceof User) {
            return;
        }

        $student = Student::query()->firstOrNew(['user_id' => $user->getKey()]);

        if (! $student->exists) {
            $student->forceFill([
                'legal_name' => $user->name,
                'student_number' => 'NIS-DEV-0001',
                'status' => StudentLifecycleStatus::Active,
                'admission_date' => now()->subYear()->startOfYear()->toDateString(),
                'birth_date' => now()->subYears(13)->toDateString(),
                'gender' => 'L',
                'birth_place' => 'Bandung',
            ])->save();
        }
    }
}
