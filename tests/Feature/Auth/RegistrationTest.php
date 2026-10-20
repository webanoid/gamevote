<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'test_user',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'username' => 'test_user',
        ]);
    }

    public function test_the_browser_timezone_is_stored_at_registration()
    {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'test_user',
            'timezone' => 'Australia/Sydney',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertDatabaseHas('users', ['username' => 'test_user', 'timezone' => 'Australia/Sydney']);
    }

    public function test_the_timezone_defaults_to_the_app_timezone_when_not_sent()
    {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'test_user',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertDatabaseHas('users', ['username' => 'test_user', 'timezone' => 'UTC']);
    }

    public function test_an_invalid_timezone_is_rejected()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'test_user',
            'timezone' => 'Mars/Olympus_Mons',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['timezone' => 'The timezone field must be a valid timezone.']);
        $this->assertGuest();
    }

    public function test_usernames_are_stored_in_lowercase()
    {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'Test_User',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertDatabaseHas('users', ['username' => 'test_user']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidUsernames(): array
    {
        return [
            'missing' => ['', 'The username field is required.'],
            'too short' => ['ab', 'The username field must be at least 3 characters.'],
            'too long' => [str_repeat('a', 31), 'The username field must not be greater than 30 characters.'],
            'contains a space' => ['test user', 'The username field must only contain letters, numbers, dashes, and underscores.'],
            'contains an at sign' => ['test@example.com', 'The username field must only contain letters, numbers, dashes, and underscores.'],
        ];
    }

    #[DataProvider('invalidUsernames')]
    public function test_registration_requires_a_valid_username(string $username, string $message)
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => $username,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['username' => $message]);
        $this->assertGuest();
    }

    public function test_usernames_must_be_unique_regardless_of_case()
    {
        User::factory()->create(['username' => 'test_user']);

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'TEST_USER',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['username' => 'The username has already been taken.']);
        $this->assertGuest();
    }
}
