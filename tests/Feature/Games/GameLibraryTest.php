<?php

use App\Models\DailyAction;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\FakeEssenseApi;

test('games are listed most voted first with ties ordered by name', function () {
    FakeEssenseApi::fake([
        ['name' => 'Tetris', 'votes' => 1],
        ['name' => 'zelda', 'votes' => 4],
        ['name' => 'Asteroids', 'votes' => 1],
        ['name' => 'Chess', 'votes' => 0],
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('games.0.name', 'zelda')
            ->where('games.1.name', 'Asteroids')
            ->where('games.2.name', 'Tetris')
            ->where('games.3.name', 'Chess')
            ->where('libraryError', null));
});

test('games the user voted for are marked', function () {
    $api = FakeEssenseApi::fake([['name' => 'Zelda', 'votes' => 1], ['name' => 'Tetris']]);
    $user = User::factory()->create();
    Vote::factory()->for($user)->create(['game_id' => $api->idOf('Zelda')]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('games.0.name', 'Zelda')
            ->where('games.0.voted', true)
            ->where('games.1.voted', false));
});

test('the page still loads with a friendly error when the api is unreachable', function () {
    FakeEssenseApi::unreachable();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('games', null)
            ->where('libraryError', 'The game library is not responding right now. Please try again in a moment.'));
});

test('a rejected api key from .env is explained instead of reported as an outage', function () {
    Http::fake(['essense.test/*' => Http::response(['error' => 'INVALID_KEY'], 403)]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('libraryError', 'The game library rejected the API key in API_KEY_ESSENCE. Update it in .env, or remove it to let the app get its own key, then restart the app.'));
});

test('the allowance shows what the user spent today and when it resets', function () {
    $this->travelTo('2026-10-20 15:00:00');
    FakeEssenseApi::fake();
    $user = User::factory()->create(['timezone' => 'Australia/Sydney']);
    DailyAction::factory()->for($user)->create(['action_date' => '2026-10-21', 'game_id' => 7]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('allowance.available', false)
            ->where('allowance.used_for', 'vote')
            ->where('allowance.game_id', 7)
            ->where('allowance.resets_at', '2026-10-22T00:00:00+11:00'));
});
