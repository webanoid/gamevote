<?php

namespace App\Actions\Games;

use App\Enums\DailyActionType;
use App\Exceptions\GameActionException;
use App\Models\User;
use App\Services\DailyAllowance;
use App\Services\Essense\EssenseApiException;
use App\Services\Essense\EssenseClient;
use App\Services\Essense\GameData;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AddGame
{
    public function __construct(
        private readonly EssenseClient $client,
        private readonly DailyAllowance $allowance,
    ) {}

    /**
     * Add a game to the library, spending the user's daily allowance, and return its ID.
     *
     * The API accepts duplicate names, so titles are compared case-insensitively against the
     * live list. A lock serialises adds so two people cannot add the same title at once.
     */
    public function handle(User $user, string $name): int
    {
        $name = Str::squish($name);

        $this->allowance->ensureAvailable($user);

        try {
            return Cache::lock('games:add', 15)->block(5, fn () => $this->addUnlessDuplicate($user, $name));
        } catch (LockTimeoutException) {
            throw GameActionException::busy();
        }
    }

    private function addUnlessDuplicate(User $user, string $name): int
    {
        try {
            $existing = collect($this->client->games())
                ->first(fn (GameData $game) => Str::lower(Str::squish($game->name)) === Str::lower($name));
        } catch (EssenseApiException $exception) {
            report($exception);

            throw GameActionException::fromApiFailure($exception);
        }

        if ($existing !== null) {
            throw GameActionException::duplicateTitle($existing->name);
        }

        $action = $this->allowance->claim($user, DailyActionType::AddGame);

        try {
            $gameId = $this->client->add($name);
        } catch (EssenseApiException $exception) {
            $action?->delete();
            report($exception);

            throw GameActionException::fromApiFailure($exception);
        }

        $action?->update(['game_id' => $gameId]);

        return $gameId;
    }
}
