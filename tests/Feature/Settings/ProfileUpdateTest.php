<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'username' => 'test_user',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test_user', $user->username);
    }

    public function test_username_is_stored_in_lowercase_when_updated()
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'username' => 'Test_User',
            ]);

        $this->assertSame('test_user', $user->refresh()->username);
    }

    public function test_user_can_keep_their_current_username()
    {
        $user = User::factory()->create(['username' => 'test_user']);

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'username' => 'test_user',
            ]);

        $response->assertSessionHasNoErrors();
    }

    public function test_username_cannot_be_changed_to_another_users_username()
    {
        User::factory()->create(['username' => 'taken_name']);
        $user = User::factory()->create(['username' => 'test_user']);

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'username' => 'Taken_Name',
            ]);

        $response
            ->assertSessionHasErrors(['username' => 'The username has already been taken.'])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('test_user', $user->refresh()->username);
    }

    public function test_username_must_only_contain_letters_numbers_dashes_and_underscores()
    {
        $user = User::factory()->create(['username' => 'test_user']);

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'username' => 'test user',
            ]);

        $response->assertSessionHasErrors(['username' => 'The username field must only contain letters, numbers, dashes, and underscores.']);
        $this->assertSame('test_user', $user->refresh()->username);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
