<?php

use App\Enums\DailyActionType;
use App\Models\DailyAction;
use App\Models\User;
use App\Models\Vote;
use Tests\FakeEssenseApi;

test('a user can vote for a game, which spends their daily allowance', function () {
    $api = FakeEssenseApi::fake([['name' => 'Zelda', 'votes' => 2]]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('games.vote.store', 1))
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast.message', 'Your vote was counted.');

    expect($api->game(1)['votes'])->toBe(3)
        ->and($user->votes()->sole()->game_id)->toBe(1)
        ->and($user->dailyActions()->sole()->type)->toBe(DailyActionType::Vote);
});

test('a user can only vote once per day', function () {
    $api = FakeEssenseApi::fake([['name' => 'Zelda'], ['name' => 'Tetris']]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('games.vote.store', 1));
    $this->post(route('games.vote.store', 2))
        ->assertInertiaFlash('toast.message', 'You have already used today\'s vote or new game. You can take another action after midnight.');

    expect($api->game(2)['votes'])->toBe(0);
});

test('a user can vote again the next day', function () {
    $api = FakeEssenseApi::fake([['name' => 'Zelda'], ['name' => 'Tetris']]);
    $user = User::factory()->create();

    $this->travelTo('2026-10-20 09:00:00');
    $this->actingAs($user)->post(route('games.vote.store', 1));

    $this->travelTo('2026-10-21 09:00:00');
    $this->post(route('games.vote.store', 2))->assertInertiaFlash('toast.type', 'success');

    expect($api->game(2)['votes'])->toBe(1);
});

test('a user cannot vote for the same game twice, even on another day', function () {
    $api = FakeEssenseApi::fake([['name' => 'Zelda', 'votes' => 1]]);
    $user = User::factory()->create();
    Vote::factory()->for($user)->create(['game_id' => 1]);

    $this->actingAs($user)
        ->post(route('games.vote.store', 1))
        ->assertInertiaFlash('toast.message', 'You have already voted for this game.');

    expect($api->game(1)['votes'])->toBe(1)
        ->and($user->dailyActions()->exists())->toBeFalse();
});

test('voting for a game that no longer exists does not spend the allowance', function () {
    FakeEssenseApi::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('games.vote.store', 99))
        ->assertInertiaFlash('toast.message', 'That game is no longer in the library. The list has been refreshed.');

    expect($user->votes()->exists())->toBeFalse()
        ->and($user->dailyActions()->exists())->toBeFalse();
});

test('a failed vote is rolled back so the allowance is not spent', function () {
    FakeEssenseApi::unreachable();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('games.vote.store', 1))
        ->assertInertiaFlash('toast.message', 'The game library is not responding right now. Please try again in a moment.');

    expect($user->votes()->exists())->toBeFalse()
        ->and($user->dailyActions()->exists())->toBeFalse();
});

test('removing a vote cast today gives the allowance back', function () {
    $api = FakeEssenseApi::fake([['name' => 'Zelda'], ['name' => 'Tetris']]);
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('games.vote.store', 1));

    $this->delete(route('games.vote.destroy', 1))
        ->assertInertiaFlash('toast.message', 'Your vote was removed. You can vote or add a game again today.');

    expect($api->game(1)['votes'])->toBe(0)
        ->and($user->votes()->exists())->toBeFalse();

    $this->post(route('games.vote.store', 2))->assertInertiaFlash('toast.type', 'success');
});

test('removing a vote from an earlier day does not refund today\'s action', function () {
    $api = FakeEssenseApi::fake([['name' => 'Zelda', 'votes' => 1], ['name' => 'Tetris']]);
    $user = User::factory()->create();
    Vote::factory()->for($user)->create(['game_id' => 1]);
    DailyAction::factory()->for($user)->create(['action_date' => now()->subDay()->toDateString(), 'game_id' => 1]);
    DailyAction::factory()->for($user)->addedGame()->create(['game_id' => 2]);

    $this->actingAs($user)
        ->delete(route('games.vote.destroy', 1))
        ->assertInertiaFlash('toast.message', 'Your vote was removed.');

    expect($api->game(1)['votes'])->toBe(0)
        ->and($user->dailyActions()->count())->toBe(2);
});

test('a user cannot remove a vote they did not cast', function () {
    $api = FakeEssenseApi::fake([['name' => 'Zelda', 'votes' => 1]]);
    Vote::factory()->create(['game_id' => 1]);

    $this->actingAs(User::factory()->create())
        ->delete(route('games.vote.destroy', 1))
        ->assertInertiaFlash('toast.message', 'You have not voted for this game.');

    expect($api->game(1)['votes'])->toBe(1)
        ->and(Vote::query()->count())->toBe(1);
});

test('a vote is kept when the api fails to remove it', function () {
    FakeEssenseApi::unreachable();
    $user = User::factory()->create();
    Vote::factory()->for($user)->create(['game_id' => 1]);

    $this->actingAs($user)
        ->delete(route('games.vote.destroy', 1))
        ->assertInertiaFlash('toast.message', 'The game library is not responding right now. Please try again in a moment.');

    expect($user->votes()->exists())->toBeTrue();
});
