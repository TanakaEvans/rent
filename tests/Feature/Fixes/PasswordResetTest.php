<?php

namespace Tests\Feature\Fixes;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function tenant(): User
    {
        return User::where('email', 'tenant@dzimba.local')->firstOrFail();
    }

    public function test_guest_can_view_the_forgot_password_page(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_requesting_a_reset_for_a_known_email_sends_the_link(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => $this->tenant()->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($this->tenant(), ResetPassword::class);
    }

    public function test_unknown_email_gets_the_same_generic_response_and_no_mail(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@nowhere.test'])
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_guest_can_view_the_reset_page_with_a_token(): void
    {
        $this->get(route('password.reset', ['token' => 'sometoken', 'email' => $this->tenant()->email]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/ResetPassword')
                ->where('token', 'sometoken')
                ->where('email', $this->tenant()->email));
    }

    public function test_a_valid_token_resets_the_password_clears_lockout_and_stamps_changed(): void
    {
        $user = $this->tenant();
        $user->forceFill(['failed_login_attempts' => 7, 'password_changed_at' => null])->save();
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'BrandNewPass123',
            'password_confirmation' => 'BrandNewPass123',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('BrandNewPass123', $user->password));
        $this->assertSame(0, (int) $user->failed_login_attempts);
        $this->assertNotNull($user->password_changed_at);
    }

    public function test_the_reset_lets_the_user_sign_in_with_the_new_password(): void
    {
        $user = $this->tenant();
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'BrandNewPass123',
            'password_confirmation' => 'BrandNewPass123',
        ]);

        $this->post(route('login.submit'), [
            'login' => $user->email,
            'password' => 'BrandNewPass123',
        ]);

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_an_invalid_token_is_rejected_and_the_password_is_unchanged(): void
    {
        $user = $this->tenant();
        $original = $user->password;

        $this->post(route('password.store'), [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'BrandNewPass123',
            'password_confirmation' => 'BrandNewPass123',
        ])->assertSessionHasErrors('email');

        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_a_weak_new_password_is_rejected(): void
    {
        $user = $this->tenant();
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertSessionHasErrors('password');
    }

    public function test_signed_in_users_are_redirected_away_from_reset_pages(): void
    {
        $this->actingAs($this->tenant());

        $this->get(route('password.request'))->assertRedirect();
        $this->get(route('password.reset', ['token' => 'x']))->assertRedirect();
    }
}
