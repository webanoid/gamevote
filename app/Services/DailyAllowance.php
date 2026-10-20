<?php

namespace App\Services;

use App\Enums\DailyActionType;
use App\Exceptions\GameActionException;
use App\Models\DailyAction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Enforces the daily rule: each user may cast one vote OR add one game per local calendar day.
 *
 * The rule is backed by a unique index on (user_id, action_date), so two concurrent
 * requests cannot both spend the same day's allowance.
 */
class DailyAllowance
{
    /**
     * Get the action the user has taken today, if any.
     */
    public function usedToday(User $user): ?DailyAction
    {
        return $user->dailyActions()
            ->whereDate('action_date', $user->localToday()->toDateString())
            ->first();
    }

    /**
     * Throw if the user has already used today's allowance.
     */
    public function ensureAvailable(User $user): void
    {
        if ($this->usedToday($user) !== null) {
            throw GameActionException::dailyLimitReached();
        }
    }

    /**
     * Spend today's allowance on the given action.
     */
    public function claim(User $user, DailyActionType $type, ?int $gameId = null): DailyAction
    {
        try {
            return $user->dailyActions()->create([
                'action_date' => $user->localToday()->toDateString(),
                'type' => $type,
                'game_id' => $gameId,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw GameActionException::dailyLimitReached();
        }
    }

    /**
     * Give back today's allowance if it was spent on this action for this game.
     *
     * Actions from earlier days stay recorded, so undoing them never grants an extra action.
     */
    public function refundToday(User $user, DailyActionType $type, int $gameId): void
    {
        $user->dailyActions()
            ->whereDate('action_date', $user->localToday()->toDateString())
            ->where('type', $type)
            ->where('game_id', $gameId)
            ->delete();
    }

    /**
     * Refund every user who spent today's allowance on a game that has been removed.
     */
    public function refundRemovedGame(int $gameId): void
    {
        DailyAction::query()
            ->where('game_id', $gameId)
            ->with('user')
            ->get()
            ->filter(fn (DailyAction $action) => $action->action_date->toDateString() === $action->user->localToday()->toDateString())
            ->each->delete();
    }

    /**
     * Refund every action taken today on a game, for every user.
     *
     * Actions without a game yet are adds still in progress; they keep their claim.
     */
    public function refundEveryoneToday(): void
    {
        DailyAction::query()
            ->whereNotNull('game_id')
            ->whereDate('action_date', '>=', now()->subDay()->toDateString())
            ->with('user')
            ->get()
            ->filter(fn (DailyAction $action) => $action->action_date->toDateString() === $action->user->localToday()->toDateString())
            ->each->delete();
    }

    /**
     * Describe the user's allowance for today.
     *
     * @return array{available: bool, used_for: string|null, game_id: int|null, resets_at: string}
     */
    public function status(User $user): array
    {
        $action = $this->usedToday($user);

        return [
            'available' => $action === null,
            'used_for' => $action?->type->value,
            'game_id' => $action?->game_id,
            'resets_at' => $this->resetsAt($user)->toIso8601String(),
        ];
    }

    /**
     * Get the moment the user's allowance next resets: their next local midnight.
     */
    public function resetsAt(User $user): CarbonImmutable
    {
        return $user->localToday()->addDay();
    }
}
