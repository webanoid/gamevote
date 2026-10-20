import { Head, router } from '@inertiajs/react';
import { AlertCircle, Gamepad2, RotateCw } from 'lucide-react';
import { useState } from 'react';
import AddGameForm from '@/components/add-game-form';
import DailyAllowanceNotice from '@/components/daily-allowance-notice';
import GameListItem from '@/components/game-list-item';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
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
    return (
        <>
            <Head title="Game library" />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Game library"
                    description="Suggest games for the office library and vote for the ones you want most."
                />

                <DailyAllowanceNotice
                    allowance={allowance}
                    games={games}
                    timezone={timezone}
                />

                <AddGameForm disabled={!allowance.available} />

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
                        <ol className="divide-y overflow-hidden rounded-xl border">
                            {games.map((game, index) => (
                                <GameListItem
                                    key={game.id}
                                    game={game}
                                    rank={index + 1}
                                    canVote={allowance.available}
                                />
                            ))}
                        </ol>
                    )}
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Game library',
            href: dashboard(),
        },
    ],
};
