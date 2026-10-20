<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_usernames_are_case_insensitive_when_logging_in()
    {
        User::factory()->create(['username' => 'test_user']);

        $this->post(route('login.store'), [
            'username' => 'Test_User',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_an_email_field()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->username,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(['username' => 'The username field is required.']);
        $this->assertGuest();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function removedEmailRoutes(): array
    {
        return [
            'forgot password form' => ['get', '/forgot-password'],
            'send password reset link' => ['post', '/forgot-password'],
            'reset password' => ['post', '/reset-password'],
            'email verification notice' => ['get', '/email/verify'],
            'resend verification email' => ['post', '/email/verification-notification'],
        ];
    }

    #[DataProvider('removedEmailRoutes')]
    public function test_email_based_routes_are_not_available(string $method, string $uri)
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->{$method}($uri);

        $response->assertNotFound();
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->username, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}
