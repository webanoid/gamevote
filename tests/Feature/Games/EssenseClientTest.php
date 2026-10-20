<?php

use App\Services\Essense\EssenseApiException;
use App\Services\Essense\EssenseClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\FakeEssenseApi;

test('requests are form encoded and include the api key', function () {
    FakeEssenseApi::fake([['name' => 'Zelda']]);

    app(EssenseClient::class)->vote(1);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://essense.test/games/vote'
        && $request->method() === 'POST'
        && $request->isForm()
        && $request['api_key'] === FakeEssenseApi::KEY
        && (int) $request['id'] === 1);
});

test('games are listed from the library', function () {
    FakeEssenseApi::fake([['name' => 'Zelda', 'votes' => 3], ['name' => 'Tetris']]);

    $games = app(EssenseClient::class)->games();

    expect($games)->toHaveCount(2)
        ->and($games[0]->name)->toBe('Zelda')
        ->and($games[0]->votes)->toBe(3);
});

test('a malformed game in the list is reported as an api failure', function () {
    Http::fake(['essense.test/*' => Http::response(['games' => [['id' => 1, 'name' => null, 'votes' => 0]]])]);

    app(EssenseClient::class)->games();
})->throws(EssenseApiException::class, 'malformed game');

test('finding an unknown game returns null', function () {
    FakeEssenseApi::fake();

    expect(app(EssenseClient::class)->find(999))->toBeNull();
});

test('a rejected add throws instead of returning the sentinel id', function () {
    Http::fake(['essense.test/*' => Http::response(['id' => -1])]);

    app(EssenseClient::class)->add('Zelda');
})->throws(EssenseApiException::class, 'could not add that game');

test('voting for an unknown game reports failure', function () {
    FakeEssenseApi::fake();

    expect(app(EssenseClient::class)->vote(999))->toBeFalse();
});

test('api error codes are surfaced on the exception', function () {
    Http::fake(['essense.test/*' => Http::response(['error' => 'INVALID_KEY'], 403)]);

    try {
        app(EssenseClient::class)->games();
        $this->fail('Expected an EssenseApiException.');
    } catch (EssenseApiException $exception) {
        expect($exception->errorCode)->toBe('INVALID_KEY');
    }
});

test('reads are retried once when the api is unreachable', function () {
    FakeEssenseApi::unreachable();

    expect(fn () => app(EssenseClient::class)->games())->toThrow(EssenseApiException::class, 'could not be reached');

    Http::assertSentCount(2);
});

test('votes are not retried so a vote is never counted twice', function () {
    FakeEssenseApi::unreachable();

    expect(fn () => app(EssenseClient::class)->vote(1))->toThrow(EssenseApiException::class);

    Http::assertSentCount(1);
});
