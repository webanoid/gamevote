<?php

namespace App\Actions\Games;

use App\Enums\DailyActionType;
use App\Exceptions\GameActionException;
use App\Models\User;
use App\Services\DailyAllowance;
use App\Services\Essense\EssenseApiException;
use App\Services\Essense\EssenseClient;
use Illuminate\Support\Facades\DB;

class RemoveVote
{
    public function __construct(
        private readonly EssenseClient $client,
        private readonly DailyAllowance $allowance,
    ) {}

    /**
     * Remove the user's own vote from a game. A vote cast today gives today's allowance back.
     */
    public function handle(User $user, int $gameId): void
    {
        $vote = $user->votes()->where('game_id', $gameId)->first();

        if ($vote === null) {
            throw GameActionException::notVoted();
        }

        try {
            // false means the game is already gone from the API; the local vote is stale either way.
            $this->client->removeVote($gameId);
        } catch (EssenseApiException $exception) {
            report($exception);

            throw GameActionException::fromApiFailure($exception);
        }

        DB::transaction(function () use ($user, $vote, $gameId) {
            $vote->delete();
            $this->allowance->refundToday($user, DailyActionType::Vote, $gameId);
        });
    }
}
