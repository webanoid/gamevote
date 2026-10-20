<?php

namespace App\Http\Controllers;

use App\Actions\Games\CastVote;
use App\Actions\Games\RemoveVote;
use App\Services\DailyAllowance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GameVoteController extends Controller
{
    /**
     * Vote for a game.
     */
    public function store(Request $request, int $game, CastVote $castVote): RedirectResponse
    {
        $castVote->handle($request->user(), $game);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Your vote was counted.']);

        return to_route('dashboard');
    }

    /**
     * Remove the user's vote from a game.
     */
    public function destroy(Request $request, int $game, RemoveVote $removeVote, DailyAllowance $allowance): RedirectResponse
    {
        $removeVote->handle($request->user(), $game);

        $message = $allowance->usedToday($request->user()) === null
            ? 'Your vote was removed. You can vote or add a game again today.'
            : 'Your vote was removed.';

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('dashboard');
    }
}
