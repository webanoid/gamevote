<?php

namespace Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * An in-memory stand-in for the Essense code challenge API that mirrors its real behaviour:
 * form-encoded bodies, `{"error": CODE}` for key problems, HTTP 200 sentinels (`{}`,
 * `{"success": false}`, `{"id": -1}`) for everything else, ids that are never reused,
 * votes that never drop below zero, a list returned in id order rather than by votes, and
 * keys issued by `api-key/generate` that all stop working when the server restarts.
 */
class FakeEssenseApi
{
    public const KEY = 'test-key';

    /** @var array<int, array{id: int, name: string, votes: int}> */
    private array $games = [];

    private int $lastId = 0;

    /** @var list<string> */
    private array $validKeys = [self::KEY];

    /** @var list<string> */
    private array $issuedKeys = [];

    /**
     * @param  list<array{name: string, votes?: int}>  $games
     */
    public static function fake(array $games = []): self
    {
        $api = new self;

        foreach ($games as $game) {
            $id = ++$api->lastId;
            $api->games[$id] = ['id' => $id, 'name' => $game['name'], 'votes' => $game['votes'] ?? 0];
        }

        Http::fake(['essense.test/*' => fn (Request $request) => $api->handle($request)]);

        return $api;
    }

    /**
     * Make every request fail as if the API were unreachable.
     */
    public static function unreachable(): void
    {
        Http::fake(['essense.test/*' => Http::failedConnection()]);
    }

    /**
     * @return array{id: int, name: string, votes: int}|null
     */
    public function game(int $id): ?array
    {
        return $this->games[$id] ?? null;
    }

    public function idOf(string $name): int
    {
        foreach ($this->games as $game) {
            if ($game['name'] === $name) {
                return $game['id'];
            }
        }

        throw new \OutOfBoundsException("No fake game named [{$name}].");
    }

    public function count(): int
    {
        return count($this->games);
    }

    /**
     * @return list<string>
     */
    public function issuedKeys(): array
    {
        return $this->issuedKeys;
    }

    /**
     * Simulate the API server restarting: every key stops working and the library starts over.
     *
     * @param  list<array{name: string, votes?: int}>  $games  Games a new key's library should contain.
     */
    public function restart(array $games = []): void
    {
        $this->validKeys = [];
        $this->games = [];
        $this->lastId = 0;

        foreach ($games as $game) {
            $id = ++$this->lastId;
            $this->games[$id] = ['id' => $id, 'name' => $game['name'], 'votes' => $game['votes'] ?? 0];
        }
    }

    private function handle(Request $request): mixed
    {
        $data = $request->data();
        $endpoint = trim((string) parse_url($request->url(), PHP_URL_PATH), '/');

        if ($endpoint === 'api-key/generate') {
            $key = bin2hex(random_bytes(16));
            $this->validKeys[] = $this->issuedKeys[] = $key;

            return Http::response(['key' => $key]);
        }

        if (! isset($data['api_key'])) {
            return Http::response(['error' => 'MISSING_KEY'], 400);
        }

        if (! in_array($data['api_key'], $this->validKeys, true)) {
            return Http::response(['error' => 'INVALID_KEY'], 403);
        }

        $id = (int) ($data['id'] ?? 0);
        $exists = isset($this->games[$id]);

        return Http::response(match ($endpoint) {
            'games/list' => ['games' => array_values($this->games)],
            'games/search' => $exists ? $this->games[$id] : [],
            'games/add' => $this->add((string) ($data['name'] ?? '')),
            'games/remove' => $this->remove($id),
            'games/vote' => $this->adjustVotes($id, 1),
            'games/removeVote' => $this->adjustVotes($id, -1),
            default => throw new \LogicException("Unexpected endpoint [{$endpoint}]."),
        });
    }

    /**
     * @return array{id: int}
     */
    private function add(string $name): array
    {
        if ($name === '') {
            return ['id' => -1];
        }

        $id = ++$this->lastId;
        $this->games[$id] = ['id' => $id, 'name' => $name, 'votes' => 0];

        return ['id' => $id];
    }

    /**
     * @return array{success: bool}
     */
    private function remove(int $id): array
    {
        if (! isset($this->games[$id])) {
            return ['success' => false];
        }

        unset($this->games[$id]);

        return ['success' => true];
    }

    /**
     * @return array{success: bool}
     */
    private function adjustVotes(int $id, int $by): array
    {
        if (! isset($this->games[$id])) {
            return ['success' => false];
        }

        $this->games[$id]['votes'] = max(0, $this->games[$id]['votes'] + $by);

        return ['success' => true];
    }
}
