<?php

use App\Enums\DailyActionType;
use App\Models\DailyAction;
use App\Models\User;
use App\Models\Vote;
use Tests\FakeEssenseApi;

test('any user can remove a game, which discards its votes', function () {
    $api = FakeEssenseApi::fake([['name' => 'Zelda', 'votes' => 2], ['name' => 'Tetris', 'votes' => 1]]);
    Vote::factory()->count(2)->create(['game_id' => 1]);
    Vote::factory()->create(['game_id' => 2]);

    $this->actingAs(User::factory()->create())
        ->delete(route('games.destroy', 1))
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast.message', 'Game removed from the library.');

    expect($api->game(1))->toBeNull()
        ->and(Vote::query()->pluck('game_id')->all())->toBe([2]);
});

test('removing a game refunds anyone who spent today\'s allowance on it', function () {
    FakeEssenseApi::fake([['name' => 'Zelda', 'votes' => 1]]);
    $adder = User::factory()->create();
    $voter = User::factory()->create();
    DailyAction::factory()->for($adder)->addedGame()->create(['game_id' => 1]);
    DailyAction::factory()->for($voter)->create(['type' => DailyActionType::Vote, 'game_id' => 1]);

    $this->actingAs(User::factory()->create())->delete(route('games.destroy', 1));

    expect(DailyAction::query()->exists())->toBeFalse();
});

test('removing a game does not refund actions from earlier days', function () {
    FakeEssenseApi::fake([['name' => 'Zelda', 'votes' => 1]]);
    DailyAction::factory()->create(['action_date' => now()->subDay()->toDateString(), 'game_id' => 1]);

    $this->actingAs(User::factory()->create())->delete(route('games.destroy', 1));

    expect(DailyAction::query()->count())->toBe(1);
});

test('removing a game that is already gone tells the user', function () {
    FakeEssenseApi::fake();

    $this->actingAs(User::factory()->create())
        ->delete(route('games.destroy', 99))
        ->assertInertiaFlash('toast.message', 'That game is no longer in the library. The list has been refreshed.');
});

test('votes are kept when the api fails to remove the game', function () {
    FakeEssenseApi::unreachable();
    Vote::factory()->create(['game_id' => 1]);

    $this->actingAs(User::factory()->create())
        ->delete(route('games.destroy', 1))
        ->assertInertiaFlash('toast.message', 'The game library is not responding right now. Please try again in a moment.');

    expect(Vote::query()->count())->toBe(1);
});
