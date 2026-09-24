<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_active_user_can_log_in(): void
    {
        $user = User::factory()->create([
            'password' => 'correct-secure-password',
        ]);

        $response = $this->post(route('login'), [
            'email' => strtoupper($user->email),
            'password' => 'correct-secure-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_login_is_rejected_without_revealing_account_state(): void
    {
        $user = User::factory()->create();

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-secure-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        $this->assertGuest();
    }

    public function test_authentication_forms_include_csrf_tokens(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="_token"', false);
    }

    public function test_login_regenerates_the_session_id(): void
    {
        $user = User::factory()->create([
            'password' => 'correct-secure-password',
        ]);

        $this->get(route('login'));
        $sessionIdBeforeLogin = session()->getId();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-secure-password',
        ]);

        $this->assertNotSame($sessionIdBeforeLogin, session()->getId());
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create([
            'password' => 'correct-secure-password',
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-secure-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('login'))->post(route('login'), [
                'email' => 'rate-limit@example.test',
                'password' => 'wrong-secure-password',
            ])->assertRedirect(route('login'));
        }

        $this->from(route('login'))->post(route('login'), [
            'email' => 'rate-limit@example.test',
            'password' => 'wrong-secure-password',
        ])->assertTooManyRequests();
    }

    public function test_password_reset_link_flow_sends_a_generic_response(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_password_reset_request_does_not_reveal_account_existence(): void
    {
        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'missing@example.test',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('status');
        $response->assertSessionMissing('errors');
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create();
        $oldRememberToken = $user->remember_token;
        $token = Password::broker()->createToken($user);
        $newPassword = 'new-secure-password-123';

        $response = $this->from(route('password.reset', $token))->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
        $this->assertNotSame($oldRememberToken, $user->fresh()->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_password_reset_invalidates_existing_database_sessions(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        $newPassword = 'new-secure-password-123';
        $sessionId = 'existing-session-for-test';

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test-agent',
            'payload' => 'test-payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('sessions', [
            'id' => $sessionId,
        ]);
    }

    public function test_unverified_user_is_sent_to_verification_notice_after_login(): void
    {
        $user = User::factory()->unverified()->create([
            'password' => 'correct-secure-password',
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-secure-password',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_verification_email_can_be_resent(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'));

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('status');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_signed_verification_link_verifies_email(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => sha1($user->email),
        ]);

        $response = $this->actingAs($user)->get($url);

        $response->assertRedirect(route('dashboard'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_inactive_account_cannot_access_protected_area_even_with_existing_session(): void
    {
        $user = User::factory()->create([
            'status' => AccountStatus::Suspended,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
