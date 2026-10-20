<?php

use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\FakeEssenseApi;

beforeEach(function () {
    config(['games.remove_limits' => true]);
});

test('with limits removed a user can add games and vote as often as they like', function () {
    $api = FakeEssenseApi::fake([['name' => 'Zelda'], ['name' => 'Tetris']]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('games.store'), ['name' => 'Chess'])->assertSessionHasNoErrors();
    $this->post(route('games.store'), ['name' => 'Go'])->assertSessionHasNoErrors();
    $this->post(route('games.vote.store', 1))->assertInertiaFlash('toast.type', 'success');
    $this->post(route('games.vote.store', 2))->assertInertiaFlash('toast.type', 'success');

    expect($api->count())->toBe(4)
        ->and($api->game(1)['votes'])->toBe(1)
        ->and($api->game(2)['votes'])->toBe(1)
        ->and($user->dailyActions()->exists())->toBeFalse();
});

test('duplicate titles are still rejected with limits removed', function () {
    $api = FakeEssenseApi::fake([['name' => 'Zelda']]);

    $this->actingAs(User::factory()->create())
        ->post(route('games.store'), ['name' => 'ZELDA'])
        ->assertSessionHasErrors(['name' => '"Zelda" is already in the library.']);

    expect($api->count())->toBe(1);
});

test('a user still gets one vote per game with limits removed', function () {
    $api = FakeEssenseApi::fake([['name' => 'Zelda', 'votes' => 1]]);
    $user = User::factory()->create();
    Vote::factory()->for($user)->create(['game_id' => 1]);

    $this->actingAs($user)
        ->post(route('games.vote.store', 1))
        ->assertInertiaFlash('toast.message', 'You have already voted for this game.');

    expect($api->game(1)['votes'])->toBe(1);
});

test('the page shows that limits are off', function () {
    FakeEssenseApi::fake();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('allowance.enforced', false)
            ->where('allowance.available', true));
});

test('the setting is ignored in production', function () {
    app()->instance('env', 'production');
    // CSRF checks are only skipped in the testing environment.
    $this->withoutMiddleware(PreventRequestForgery::class);
    $api = FakeEssenseApi::fake([['name' => 'Zelda'], ['name' => 'Tetris']]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('games.vote.store', 1));
    $this->post(route('games.vote.store', 2))
        ->assertInertiaFlash('toast.message', 'You have already used today\'s vote or new game. You can take another action after midnight.');

    expect($api->game(2)['votes'])->toBe(0);
});
