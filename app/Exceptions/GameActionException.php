<?php

namespace App\Exceptions;

use App\Services\Essense\EssenseApiException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use RuntimeException;

/**
 * A game library action that could not be completed, carrying a message safe to show the user.
 */
class GameActionException extends RuntimeException
{
    public static function dailyLimitReached(): self
    {
        return new self('You have already used today\'s vote or new game. You can take another action after midnight.');
    }

    public static function duplicateTitle(string $existingName): self
    {
        return new self("\"{$existingName}\" is already in the library.");
    }

    public static function alreadyVoted(): self
    {
        return new self('You have already voted for this game.');
    }

    public static function notVoted(): self
    {
        return new self('You have not voted for this game.');
    }

    public static function gameNotFound(): self
    {
        return new self('That game is no longer in the library. The list has been refreshed.');
    }

    public static function libraryUnavailable(): self
    {
        return new self('The game library is not responding right now. Please try again in a moment.');
    }

    public static function notConfigured(): self
    {
        return new self('The game library rejected the API key in API_KEY_ESSENCE. Update it in .env, or remove it to let the app get its own key, then restart the app.');
    }

    /**
     * Translate an API failure into the message the user should see.
     */
    public static function fromApiFailure(EssenseApiException $exception): self
    {
        return in_array($exception->errorCode, ['MISSING_KEY', 'INVALID_KEY'], true)
            ? self::notConfigured()
            : self::libraryUnavailable();
    }

    public static function busy(): self
    {
        return new self('Another game is being added right now. Please try again.');
    }

    /**
     * Send the user back with the message as an error toast.
     */
    public function render(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $this->getMessage()]);

        return back();
    }
}
