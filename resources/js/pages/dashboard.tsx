import { Head, router } from '@inertiajs/react';
import { AlertCircle, Gamepad2, RotateCw } from 'lucide-react';
import { useState } from 'react';
import AddGameForm from '@/components/add-game-form';
import DailyAllowanceNotice from '@/components/daily-allowance-notice';
import GameListItem from '@/components/game-list-item';
import SprayPaintedTitle from '@/components/spray-painted-title';
import { useFlipAnimation } from '@/hooks/use-flip-animation';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { DailyAllowance, Game } from '@/types';

type Props = {
    games: Game[] | null;
    libraryError: string | null;
    allowance: DailyAllowance;
    timezone: string;
};

function LibraryUnavailable({ message }: { message: string }) {
    const [retrying, setRetrying] = useState(false);

    return (
        <Alert variant="destructive">
            <AlertCircle />
            <AlertTitle>Couldn’t load the game library</AlertTitle>
            <AlertDescription>
                <p>{message}</p>
                <Button
                    variant="outline"
                    size="sm"
                    className="mt-2"
                    disabled={retrying}
                    onClick={() =>
                        router.reload({
                            onStart: () => setRetrying(true),
                            onFinish: () => setRetrying(false),
                        })
                    }
                >
                    {retrying ? <Spinner /> : <RotateCw />}
                    Try again
                </Button>
            </AlertDescription>
        </Alert>
    );
}

function EmptyLibrary() {
    return (
        <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed px-6 py-12 text-center">
            <Gamepad2 className="size-8 text-muted-foreground" />
            <p className="font-medium">No games yet</p>
            <p className="max-w-xs text-sm text-muted-foreground">
                Suggest the first game for the office library above.
            </p>
        </div>
    );
}

export default function Dashboard({
    games,
    libraryError,
    allowance,
    timezone,
}: Props) {
    const maxVotes = Math.max(0, ...(games ?? []).map((game) => game.votes));
    const flipRef = useFlipAnimation<HTMLLIElement>(
        (games ?? []).map((game) => game.id),
    );
    const [busy, setBusy] = useState(false);

    // Highlight rows whose votes changed (or that are new) since the previous list.
    const [previousGames, setPreviousGames] = useState(games);
    const [highlight, setHighlight] = useState({
        ids: [] as number[],
        run: 0,
    });

    if (games !== previousGames) {
        setPreviousGames(games);

        if (games && previousGames) {
            const before = new Map(
                previousGames.map((game) => [game.id, game.votes]),
            );
            const changed = games
                .filter((game) => before.get(game.id) !== game.votes)
                .map((game) => game.id);

            if (changed.length > 0) {
                setHighlight(({ run }) => ({ ids: changed, run: run + 1 }));
            }
        }
    }

    return (
        <>
            <Head title="Game library" />
            <div className="flex flex-col gap-6">
                <SprayPaintedTitle
                    title="Game Library"
                    description="Suggest games for the office library and vote for the ones you want most."
                />

                <DailyAllowanceNotice
                    allowance={allowance}
                    games={games}
                    timezone={timezone}
                />

                <AddGameForm
                    disabled={!allowance.available}
                    limited={allowance.enforced}
                />

                <section aria-labelledby="games-heading" className="grid gap-3">
                    <h2 id="games-heading" className="text-sm font-medium">
                        Most wanted
                        {games && games.length > 0 && (
                            <span className="ml-1 text-muted-foreground">
                                ({games.length})
                            </span>
                        )}
                    </h2>

                    {libraryError !== null || games === null ? (
                        <LibraryUnavailable
                            message={
                                libraryError ??
                                'The game library could not be loaded.'
                            }
                        />
                    ) : games.length === 0 ? (
                        <EmptyLibrary />
                    ) : (
                        <ol
                            className="games-list relative divide-y overflow-hidden rounded-xl border"
                            data-busy={busy || undefined}
                            aria-busy={busy || undefined}
                        >
                            {games.map((game, index) => (
                                <GameListItem
                                    key={game.id}
                                    ref={flipRef(game.id)}
                                    game={game}
                                    rank={index + 1}
                                    maxVotes={maxVotes}
                                    canVote={allowance.available}
                                    highlightRun={
                                        highlight.ids.includes(game.id)
                                            ? highlight.run
                                            : null
                                    }
                                    onPendingChange={setBusy}
                                />
                            ))}
                        </ol>
                    )}
                </section>
            </div>
        </>
    );
}
