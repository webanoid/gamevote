<?php

use App\Console\Commands\SeedGames;
use Tests\FakeEssenseApi;

test('it adds ten games with random vote counts by default', function () {
    $api = FakeEssenseApi::fake();

    $this->artisan('games:seed')
        ->expectsOutputToContain('Added 10 games to the library.')
        ->assertSuccessful();

    expect($api->count())->toBe(10);

    foreach (range(1, 10) as $id) {
        expect($api->game($id)['name'])->toBeIn(SeedGames::TITLES)
            ->and($api->game($id)['votes'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(15);
    }
});

test('the number of games and the vote ceiling can be chosen', function () {
    $api = FakeEssenseApi::fake();

    $this->artisan('games:seed', ['--count' => 3, '--max-votes' => 0])->assertSuccessful();

    expect($api->count())->toBe(3)
        ->and(array_sum(array_map(fn (int $id) => $api->game($id)['votes'], [1, 2, 3])))->toBe(0);
});

test('titles already in the library are not added again', function () {
    $titles = array_slice(SeedGames::TITLES, 0, count(SeedGames::TITLES) - 2);
    $api = FakeEssenseApi::fake(array_map(fn (string $title) => ['name' => strtoupper($title)], $titles));

    $this->artisan('games:seed', ['--count' => 5])
        ->expectsOutputToContain('Only 2 new titles were available')
        ->assertSuccessful();

    expect($api->count())->toBe(count(SeedGames::TITLES));
});

test('invalid options are rejected without touching the library', function (array $options) {
    $api = FakeEssenseApi::fake();

    $this->artisan('games:seed', $options)->assertExitCode(SeedGames::INVALID);

    expect($api->count())->toBe(0);
})->with([
    'zero games' => [['--count' => 0]],
    'more games than titles' => [['--count' => count(SeedGames::TITLES) + 1]],
    'not a number' => [['--count' => 'lots']],
    'negative votes' => [['--max-votes' => -1]],
]);

test('it reports a friendly error when the api is unreachable', function () {
    FakeEssenseApi::unreachable();

    $this->artisan('games:seed')
        ->expectsOutputToContain('Seeding stopped: The game library could not be reached.')
        ->assertFailed();
});

test('in production it does nothing unless forced', function () {
    $api = FakeEssenseApi::fake();
    app()->instance('env', 'production');

    $this->artisan('games:seed', ['--no-interaction' => true])
        ->expectsOutputToContain('Command cancelled.')
        ->assertFailed();

    expect($api->count())->toBe(0);

    $this->artisan('games:seed', ['--force' => true])->assertSuccessful();

    expect($api->count())->toBe(10);
});
