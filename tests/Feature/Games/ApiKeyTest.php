<?php

use App\Models\ApiCredential;
use App\Models\DailyAction;
use App\Models\User;
use App\Models\Vote;
use App\Services\Essense\ApiKeyProvider;
use App\Services\Essense\EssenseClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\FakeEssenseApi;

/**
 * Run without API_KEY_ESSENCE, so the app manages its own key.
 */
function withoutConfiguredKey(): void
{
    config(['services.essense.key' => null]);
}

/**
 * Drop the resolved key, as a new PHP request would.
 */
function startNewRequest(): void
{
    app()->forgetInstance(ApiKeyProvider::class);
    app()->forgetInstance(EssenseClient::class);
}

test('without API_KEY_ESSENCE the app generates a key and stores it encrypted', function () {
    withoutConfiguredKey();
    $api = FakeEssenseApi::fake([['name' => 'Zelda']]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('games.0.name', 'Zelda'));

    [$key] = $api->issuedKeys();
    expect($api->issuedKeys())->toHaveCount(1)
        ->and(ApiCredential::current()->generated_key)->toBe($key)
        ->and(DB::table('api_credentials')->value('generated_key'))->not->toContain($key);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'games/list') && $request['api_key'] === $key);
});

test('the stored key is reused by later requests', function () {
    withoutConfiguredKey();
    $api = FakeEssenseApi::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'));
    startNewRequest();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('libraryError', null));

    expect($api->issuedKeys())->toHaveCount(1);
});

test('API_KEY_ESSENCE takes precedence and no key is generated', function () {
    $api = FakeEssenseApi::fake();

    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();

    expect($api->issuedKeys())->toBeEmpty()
        ->and(ApiCredential::current()->generated_key)->toBeNull();
});

test('a rejected key from API_KEY_ESSENCE is not replaced', function () {
    config(['services.essense.key' => 'revoked-key']);
    $api = FakeEssenseApi::fake();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('libraryError', 'The game library rejected the API key in API_KEY_ESSENCE. Update it in .env, or remove it to let the app get its own key, then restart the app.'));

    expect($api->issuedKeys())->toBeEmpty();
});

test('a generated key that stops working is replaced and the request retried', function () {
    withoutConfiguredKey();
    $api = FakeEssenseApi::fake();
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('dashboard'));

    $api->restart([['name' => 'Chess']]);
    startNewRequest();

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('libraryError', null)
            ->where('games.0.name', 'Chess'));

    expect($api->issuedKeys())->toHaveCount(2)
        ->and(ApiCredential::current()->generated_key)->toBe($api->issuedKeys()[1]);
});

test('a new library discards local votes and refunds today\'s actions on them', function () {
    withoutConfiguredKey();
    $api = FakeEssenseApi::fake([['name' => 'Zelda', 'votes' => 1]]);
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('dashboard'));

    Vote::factory()->for($user)->create(['game_id' => 1]);
    $today = DailyAction::factory()->for($user)->create(['game_id' => 1]);
    $yesterday = DailyAction::factory()->for($user)->create(['action_date' => now()->subDay()->toDateString(), 'game_id' => 1]);

    // Game id 1 in the new library is a different game, so the old vote must not carry over.
    $api->restart([['name' => 'Chess']]);
    startNewRequest();

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('games.0.name', 'Chess')
            ->where('games.0.voted', false)
            ->where('allowance.available', true));

    expect(Vote::query()->exists())->toBeFalse()
        ->and($today->fresh())->toBeNull()
        ->and($yesterday->fresh())->not->toBeNull();
});

test('local votes are kept while the key stays the same', function () {
    withoutConfiguredKey();
    FakeEssenseApi::fake([['name' => 'Zelda', 'votes' => 1]]);
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('dashboard'));
    Vote::factory()->for($user)->create(['game_id' => 1]);

    startNewRequest();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('games.0.voted', true));

    expect(Vote::query()->count())->toBe(1);
});

test('switching from API_KEY_ESSENCE to a generated key resets local votes', function () {
    $api = FakeEssenseApi::fake([['name' => 'Zelda', 'votes' => 1]]);
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('dashboard'));
    Vote::factory()->for($user)->create(['game_id' => 1]);

    withoutConfiguredKey();
    startNewRequest();
    $this->get(route('dashboard'))->assertOk();

    expect($api->issuedKeys())->toHaveCount(1)
        ->and(Vote::query()->exists())->toBeFalse();
});

test('the page explains when no key can be generated', function () {
    withoutConfiguredKey();
    Http::fake(['essense.test/api-key/generate' => Http::response('', 500)]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('games', null)
            ->where('libraryError', 'The game library is not responding right now. Please try again in a moment.'));
});
