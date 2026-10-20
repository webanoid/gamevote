<?php

namespace App\Actions\Games;

use App\Exceptions\GameActionException;
use App\Models\Vote;
use App\Services\DailyAllowance;
use App\Services\Essense\EssenseApiException;
use App\Services\Essense\EssenseClient;
use Illuminate\Support\Facades\DB;

class RemoveGame
{
    public function __construct(
        private readonly EssenseClient $client,
        private readonly DailyAllowance $allowance,
    ) {}

    /**
     * Remove a game from the library. Anyone may remove a game.
     *
     * Its votes are discarded, and anyone who spent today's allowance on it gets it back.
     */
    public function handle(int $gameId): void
    {
        try {
            $removed = $this->client->remove($gameId);
        } catch (EssenseApiException $exception) {
            report($exception);

            throw GameActionException::fromApiFailure($exception);
        }

        DB::transaction(function () use ($gameId) {
            $this->allowance->refundRemovedGame($gameId);
            Vote::query()->where('game_id', $gameId)->delete();
        });

        if (! $removed) {
            throw GameActionException::gameNotFound();
        }
    }
}
