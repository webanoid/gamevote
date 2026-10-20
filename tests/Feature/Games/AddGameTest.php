<?php

use App\Enums\DailyActionType;
use App\Models\DailyAction;
use App\Models\User;
use Tests\FakeEssenseApi;

test('a user can add a game, which spends their daily allowance', function () {
    $api = FakeEssenseApi::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('games.store'), ['name' => '  The   Legend of Zelda '])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast.message', 'Added "The Legend of Zelda" to the library.');

    $gameId = $api->idOf('The Legend of Zelda');
    expect($user->dailyActions()->sole())
        ->type->toBe(DailyActionType::AddGame)
        ->game_id->toBe($gameId);
});

test('duplicate titles are rejected regardless of case and spacing', function () {
    $api = FakeEssenseApi::fake([['name' => 'The Legend of Zelda']]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('games.store'), ['name' => 'the  LEGEND of zelda'])
        ->assertSessionHasErrors(['name' => '"The Legend of Zelda" is already in the library.']);

    expect($api->count())->toBe(1)
        ->and($user->dailyActions()->exists())->toBeFalse();
});

test('a user who already voted today cannot also add a game', function () {
    $api = FakeEssenseApi::fake();
    $user = User::factory()->create();
    DailyAction::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('games.store'), ['name' => 'Tetris'])
        ->assertSessionHasErrors(['name' => 'You have already used today\'s vote or new game. You can take another action after midnight.']);

    expect($api->count())->toBe(0);
});

test('the title is validated', function (mixed $name, string $message) {
    FakeEssenseApi::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('games.store'), ['name' => $name])
        ->assertSessionHasErrors(['name' => $message]);
})->with([
    'missing' => ['', 'The game title field is required.'],
    'only whitespace' => ['   ', 'The game title field is required.'],
    'too long' => [str_repeat('a', 101), 'The game title field must not be greater than 100 characters.'],
]);

test('the allowance is not spent when the api fails to add the game', function () {
    FakeEssenseApi::unreachable();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('games.store'), ['name' => 'Tetris'])
        ->assertSessionHasErrors(['name' => 'The game library is not responding right now. Please try again in a moment.']);

    expect($user->dailyActions()->exists())->toBeFalse();
});

test('the allowance resets at midnight in the user\'s own timezone', function () {
    $api = FakeEssenseApi::fake();
    $user = User::factory()->create(['timezone' => 'Australia/Sydney']);

    // 23:30 on 20 Oct in Sydney (UTC+11).
    $this->travelTo('2026-10-20 12:30:00');
    $this->actingAs($user)->post(route('games.store'), ['name' => 'Tetris'])->assertSessionHasNoErrors();

    // 23:59 the same Sydney day: still used up.
    $this->travelTo('2026-10-20 12:59:00');
    $this->post(route('games.store'), ['name' => 'Chess'])->assertSessionHasErrors('name');

    // 00:01 on 21 Oct in Sydney, though it is still 20 Oct in UTC.
    $this->travelTo('2026-10-20 13:01:00');
    $this->post(route('games.store'), ['name' => 'Chess'])->assertSessionHasNoErrors();

    expect($api->count())->toBe(2);
});

test('guests cannot add games', function () {
    $api = FakeEssenseApi::fake();

    $this->post(route('games.store'), ['name' => 'Tetris'])->assertRedirect(route('login'));

    expect($api->count())->toBe(0);
});
