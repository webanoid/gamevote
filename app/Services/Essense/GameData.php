<?php

namespace App\Services\Essense;

/**
 * A game as returned by the Essense code challenge API.
 */
readonly class GameData
{
    public function __construct(
        public int $id,
        public string $name,
        public int $votes,
    ) {}

    /**
     * Build a game from an API payload, rejecting anything that is not a well-formed game.
     *
     * @throws EssenseApiException
     */
    public static function fromArray(mixed $attributes): self
    {
        if (! is_array($attributes)
            || ! is_numeric($attributes['id'] ?? null)
            || ! is_string($attributes['name'] ?? null)
            || ! is_numeric($attributes['votes'] ?? null)) {
            throw new EssenseApiException('The game library returned a malformed game.');
        }

        return new self((int) $attributes['id'], $attributes['name'], (int) $attributes['votes']);
    }
}
