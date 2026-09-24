<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login_for_protected_area(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_role_middleware_allows_matching_role(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('admin.foundation'))
            ->assertOk()
            ->assertSee('Area administrator');
    }

    public function test_dashboard_accepts_each_fixed_role(): void
    {
        $siswa = User::factory()->create();

        $this->actingAs($siswa)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Fondasi aplikasi');
    }

    public function test_role_middleware_forbids_a_different_role(): void
    {
        $siswa = User::factory()->create();

        $this->actingAs($siswa)
            ->get(route('admin.foundation'))
            ->assertForbidden();

        $this->assertAuthenticatedAs($siswa);
    }

    public function test_user_policy_allows_administrator_to_manage_other_accounts(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->assertTrue(Gate::forUser($administrator)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($administrator)->allows('view', $target));
        $this->assertTrue(Gate::forUser($administrator)->allows('update', $target));
        $this->assertTrue(Gate::forUser($administrator)->allows('deactivate', $target));
    }

    public function test_user_policy_denies_account_management_to_non_administrator(): void
    {
        $siswa = User::factory()->create();
        $otherUser = User::factory()->guru()->create();

        $this->assertFalse(Gate::forUser($siswa)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($siswa)->allows('view', $siswa));
        $this->assertFalse(Gate::forUser($siswa)->allows('view', $otherUser));
        $this->assertFalse(Gate::forUser($siswa)->allows('update', $otherUser));
        $this->assertFalse(Gate::forUser($siswa)->allows('deactivate', $otherUser));
    }

    public function test_user_policy_does_not_allow_hard_deletion(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->assertFalse(Gate::forUser($administrator)->allows('delete', $target));
    }

    public function test_unverified_administrator_cannot_use_administrator_gate(): void
    {
        $administrator = User::factory()->administrator()->unverified()->create();

        $this->assertFalse(Gate::forUser($administrator)->allows('manage-users'));
        $this->assertFalse(Gate::forUser($administrator)->allows('access-administrator-area'));
    }

    public function test_missing_role_cannot_access_a_role_protected_area(): void
    {
        $user = User::factory()->create([
            'role' => null,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
