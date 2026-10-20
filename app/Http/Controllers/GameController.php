<?php

namespace App\Http\Controllers;

use App\Actions\Games\AddGame;
use App\Actions\Games\RemoveGame;
use App\Exceptions\GameActionException;
use App\Http\Requests\StoreGameRequest;
use App\Services\DailyAllowance;
use App\Services\Essense\EssenseApiException;
use App\Services\Essense\EssenseClient;
use App\Services\Essense\GameData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    /**
     * Show the game library, most-voted first.
     */
    public function index(Request $request, EssenseClient $client, DailyAllowance $allowance): Response
    {
        $user = $request->user();

        $games = null;
        $libraryError = null;

        try {
            $games = $client->games();
        } catch (EssenseApiException $exception) {
            report($exception);
            $libraryError = GameActionException::fromApiFailure($exception)->getMessage();
        }

        $votedGameIds = $user->votes()->pluck('game_id')->flip();

        return Inertia::render('dashboard', [
            'games' => $games === null ? null : collect($games)
                ->sort(fn (GameData $a, GameData $b) => [$b->votes, Str::lower($a->name)] <=> [$a->votes, Str::lower($b->name)])
                ->map(fn (GameData $game) => [
                    'id' => $game->id,
                    'name' => $game->name,
                    'votes' => $game->votes,
                    'voted' => $votedGameIds->has($game->id),
                ])
                ->values(),
            'libraryError' => $libraryError,
            'allowance' => $allowance->status($user),
            'timezone' => $user->timezone,
        ]);
    }

    /**
     * Add a game to the library.
     */
    public function store(StoreGameRequest $request, AddGame $addGame): RedirectResponse
    {
        $name = $request->validated('name');

        try {
            $addGame->handle($request->user(), $name);
        } catch (GameActionException $exception) {
            throw ValidationException::withMessages(['name' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Added \"{$name}\" to the library."]);

        return to_route('dashboard');
    }

    /**
     * Remove a game from the library.
     */
    public function destroy(int $game, RemoveGame $removeGame): RedirectResponse
    {
        $removeGame->handle($game);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Game removed from the library.']);

        return to_route('dashboard');
    }
}
