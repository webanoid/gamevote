<?php

namespace App\Services\Essense;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Client for the Essense code challenge REST API.
 *
 * Every endpoint takes a form-encoded body that includes the API key. The API reports
 * most failures with HTTP 200 and a sentinel body (`{}`, `{"success": false}` or
 * `{"id": -1}`), so responses are checked by shape rather than status code alone.
 *
 * The API's keys reset when its server restarts. When a key the app generated is rejected,
 * the client gets a new one and retries once; the rejected request changed nothing.
 */
class EssenseClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ApiKeyProvider $keys,
        private readonly int $timeoutSeconds = 8,
    ) {}

    /**
     * List every game in the library.
     *
     * @return list<GameData>
     */
    public function games(): array
    {
        $body = $this->post('games/list', retry: true);

        if (! isset($body['games']) || ! is_array($body['games'])) {
            throw new EssenseApiException('The game library returned an unexpected response.');
        }

        return array_values(array_map(GameData::fromArray(...), $body['games']));
    }

    /**
     * Find a game by its ID, or null when no such game exists.
     */
    public function find(int $id): ?GameData
    {
        $body = $this->post('games/search', ['id' => $id], retry: true);

        return isset($body['id'], $body['name']) ? GameData::fromArray($body) : null;
    }

    /**
     * Add a game and return its new ID.
     */
    public function add(string $name): int
    {
        $body = $this->post('games/add', ['name' => $name]);
        $id = (int) ($body['id'] ?? -1);

        if ($id < 1) {
            throw new EssenseApiException('The game library could not add that game.');
        }

        return $id;
    }

    /**
     * Remove a game. Returns false when the game does not exist.
     */
    public function remove(int $id): bool
    {
        return $this->succeeded($this->post('games/remove', ['id' => $id]));
    }

    /**
     * Add one vote to a game. Returns false when the game does not exist.
     */
    public function vote(int $id): bool
    {
        return $this->succeeded($this->post('games/vote', ['id' => $id]));
    }

    /**
     * Remove one vote from a game. Returns false when the game does not exist.
     */
    public function removeVote(int $id): bool
    {
        return $this->succeeded($this->post('games/removeVote', ['id' => $id]));
    }

    /**
     * Send a request and return the decoded JSON body.
     *
     * Only idempotent reads are retried; retrying a vote could count it twice.
     *
     * @param  array<string, scalar>  $data
     * @return array<string, mixed>
     */
    private function post(string $endpoint, array $data = [], bool $retry = false): array
    {
        $key = $this->keys->key();

        try {
            return $this->send($endpoint, $key, $data, $retry);
        } catch (EssenseApiException $exception) {
            if ($exception->errorCode !== 'INVALID_KEY' || ! $this->keys->isManaged()) {
                throw $exception;
            }

            return $this->send($endpoint, $this->keys->replace($key), $data, $retry);
        }
    }

    /**
     * @param  array<string, scalar>  $data
     * @return array<string, mixed>
     */
    private function send(string $endpoint, string $key, array $data, bool $retry): array
    {
        try {
            $response = $this->request($retry)->post($endpoint, ['api_key' => $key, ...$data]);
        } catch (ConnectionException $exception) {
            throw new EssenseApiException('The game library could not be reached.', previous: $exception);
        }

        return $this->decode($response);
    }

    private function request(bool $retry): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl)
            ->asForm()
            ->acceptJson()
            ->connectTimeout(4)
            ->timeout($this->timeoutSeconds);

        return $retry
            ? $request->retry(2, 200, fn (Throwable $exception) => $exception instanceof ConnectionException, throw: false)
            : $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $body = $response->json();

        if (is_array($body) && is_string($body['error'] ?? null)) {
            throw new EssenseApiException("The game library rejected the request ({$body['error']}).", $body['error']);
        }

        if ($response->failed() || ! is_array($body)) {
            throw new EssenseApiException("The game library responded with HTTP {$response->status()}.");
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function succeeded(array $body): bool
    {
        return ($body['success'] ?? false) === true;
    }
}
