<?php

namespace App\Actions\Games;

use App\Enums\DailyActionType;
use App\Exceptions\GameActionException;
use App\Models\User;
use App\Services\DailyAllowance;
use App\Services\Essense\EssenseApiException;
use App\Services\Essense\EssenseClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CastVote
{
    public function __construct(
        private readonly EssenseClient $client,
        private readonly DailyAllowance $allowance,
    ) {}

    /**
     * Vote for a game, spending the user's daily allowance.
     *
     * The vote and allowance are recorded first so the unique indexes reject double votes and
     * concurrent requests; if the API call then fails, both records are rolled back.
     */
    public function handle(User $user, int $gameId): void
    {
        if ($user->votes()->where('game_id', $gameId)->exists()) {
            throw GameActionException::alreadyVoted();
        }

        $this->allowance->ensureAvailable($user);

        [$action, $vote] = DB::transaction(function () use ($user, $gameId) {
            $action = $this->allowance->claim($user, DailyActionType::Vote, $gameId);

            try {
                $vote = $user->votes()->create(['game_id' => $gameId]);
            } catch (UniqueConstraintViolationException) {
                throw GameActionException::alreadyVoted();
            }

            return [$action, $vote];
        });

        try {
            $counted = $this->client->vote($gameId);
        } catch (EssenseApiException $exception) {
            $vote->delete();
            $action?->delete();
            report($exception);

            throw GameActionException::fromApiFailure($exception);
        }

        if (! $counted) {
            $vote->delete();
            $action?->delete();

            throw GameActionException::gameNotFound();
        }
    }
}
