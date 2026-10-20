<?php

namespace App\Console\Commands;

use App\Services\Essense\EssenseApiException;
use App\Services\Essense\EssenseClient;
use App\Services\Essense\GameData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Str;

#[Signature('games:seed
    {--count=10 : How many games to add}
    {--max-votes=15 : The most votes a seeded game can get}
    {--force : Run in production without asking for confirmation}')]
#[Description('Add sample games with random vote counts to the game library')]
class SeedGames extends Command
{
    use ConfirmableTrait;

    /**
     * Titles to seed from, so the library looks like a real office wishlist.
     *
     * @var list<string>
     */
    public const TITLES = [
        '7 Wonders',
        'Azul',
        'Carcassonne',
        'Catan',
        'Codenames',
        'Dixit',
        'Exploding Kittens',
        'Jackbox Party Pack 9',
        'King of Tokyo',
        'Mario Kart 8 Deluxe',
        'Overcooked! 2',
        'Pandemic',
        'Rocket League',
        'Splendor',
        'Stardew Valley',
        'Super Smash Bros. Ultimate',
        'Ticket to Ride',
        'Wingspan',
    ];

    /**
     * Execute the console command.
     *
     * Games and votes go straight to the API, so the daily rule does not apply and the votes
     * are not attributed to any user. Titles already in the library are skipped.
     */
    public function handle(EssenseClient $client): int
    {
        $count = filter_var($this->option('count'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => count(self::TITLES)]]);
        $maxVotes = filter_var($this->option('max-votes'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);

        if ($count === false || $maxVotes === false) {
            $this->components->error('--count must be between 1 and '.count(self::TITLES).', and --max-votes between 0 and 100.');

            return self::INVALID;
        }

        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $seeded = [];

        try {
            $existing = collect($client->games())->map(fn (GameData $game) => Str::lower(Str::squish($game->name)));
            $titles = collect(self::TITLES)
                ->reject(fn (string $title) => $existing->contains(Str::lower($title)))
                ->shuffle()
                ->take($count);

            foreach ($titles as $title) {
                $gameId = $client->add($title);
                $votes = random_int(0, $maxVotes);

                for ($i = 0; $i < $votes; $i++) {
                    $client->vote($gameId);
                }

                $seeded[] = [$title, $votes];
            }
        } catch (EssenseApiException $exception) {
            $this->components->error("Seeding stopped: {$exception->getMessage()}");
            $this->reportSeeded($seeded);

            return self::FAILURE;
        }

        if (count($seeded) < $count) {
            $this->components->warn('Only '.count($seeded).' new titles were available; the others are already in the library.');
        }

        $this->reportSeeded($seeded);

        return self::SUCCESS;
    }

    /**
     * @param  list<array{string, int}>  $seeded
     */
    private function reportSeeded(array $seeded): void
    {
        if ($seeded === []) {
            $this->components->info('No games were added.');

            return;
        }

        $this->table(['Game', 'Votes'], $seeded);
        $this->components->info('Added '.count($seeded).' '.Str::plural('game', count($seeded)).' to the library.');
    }
}
