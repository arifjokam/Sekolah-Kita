<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\AccountLifecycleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_can_activate_an_account_with_a_fixed_role(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->inactive()->create([
            'role' => null,
        ]);
        $service = app(AccountLifecycleService::class);

        $updatedUser = $service->activate($administrator, $target, UserRole::Guru);

        $this->assertSame(AccountStatus::Active, $updatedUser->status);
        $this->assertSame(UserRole::Guru, $updatedUser->role);
        $this->assertNotSame($target->remember_token, $updatedUser->remember_token);
    }

    public function test_non_administrator_cannot_manage_account_status(): void
    {
        $siswa = User::factory()->create();
        $target = User::factory()->create();
        $service = app(AccountLifecycleService::class);

        $this->expectException(AuthorizationException::class);

        $service->deactivate($siswa, $target);
    }

    public function test_administrator_can_suspend_and_deactivate_without_deleting_user(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $service = app(AccountLifecycleService::class);

        $suspended = $service->suspend($administrator, $target);
        $this->assertSame(AccountStatus::Suspended, $suspended->status);
        $this->assertModelExists($suspended);

        $deactivated = $service->deactivate($administrator, $target);
        $this->assertSame(AccountStatus::Inactive, $deactivated->status);
        $this->assertModelExists($deactivated);
    }

    public function test_role_changes_are_explicit_and_not_mass_assignable(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $service = app(AccountLifecycleService::class);

        $target->fill([
            'role' => UserRole::Administrator,
            'status' => AccountStatus::Active,
        ]);
        $target->save();

        $this->assertSame(UserRole::Siswa, $target->fresh()->role);

        $updatedUser = $service->changeRole($administrator, $target, UserRole::Guru);

        $this->assertSame(UserRole::Guru, $updatedUser->role);
    }
}
