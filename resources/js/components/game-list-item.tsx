import { Form } from '@inertiajs/react';
import { memo, useState } from 'react';
import { Check, ThumbsUp, Trash2, Undo2 } from 'lucide-react';
import GameController from '@/actions/App/Http/Controllers/GameController';
import GameVoteController from '@/actions/App/Http/Controllers/GameVoteController';
import MeepleVotes from '@/components/meeple-votes';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { Game } from '@/types';

function votesLabel(votes: number): string {
    return votes === 1 ? '1 vote' : `${votes} votes`;
}

function RemoveGameDialog({
    game,
    onPendingChange,
}: {
    game: Game;
    onPendingChange: (pending: boolean) => void;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="text-muted-foreground hover:text-destructive"
                    aria-label={`Remove ${game.name}`}
                    data-test="remove-game-button"
                >
                    <Trash2 />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Remove “{game.name}”?</DialogTitle>
                <DialogDescription>
                    This removes the game for everyone
                    {game.votes > 0 &&
                        ` and discards its ${votesLabel(game.votes)}`}
                    . Anyone who used today’s action on it gets that action
                    back.
                </DialogDescription>
                <Form
                    {...GameController.destroy.form(game.id)}
                    options={{ preserveScroll: true }}
                    onStart={() => onPendingChange(true)}
                    onFinish={() => onPendingChange(false)}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                                data-test="confirm-remove-game-button"
                            >
                                {processing && <Spinner />}
                                Remove game
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

type Props = {
    ref?: React.Ref<HTMLLIElement>;
    game: Game;
    rank: number;
    maxVotes: number;
    canVote: boolean;
    /** Increments each time this row's votes change, to replay the highlight; null for no highlight. */
    highlightRun: number | null;
    /** Reports when this row's request starts and finishes; the list dims the other rows meanwhile. */
    onPendingChange: (pending: boolean) => void;
};

function GameListItem({
    ref,
    game,
    rank,
    maxVotes,
    canVote,
    highlightRun,
    onPendingChange,
}: Props) {
    const [pending, setPending] = useState(false);
    const reportPending = (isPending: boolean) => {
        setPending(isPending);
        onPendingChange(isPending);
    };

    return (
        <li
            ref={ref}
            data-pending={pending || undefined}
            className={cn(
                'relative flex flex-col gap-3 bg-background p-4 transition-opacity sm:flex-row sm:items-center',
                game.voted && 'bg-primary/[0.03] dark:bg-primary/[0.06]',
            )}
            style={
                highlightRun === null
                    ? undefined
                    : {
                          animation: `${highlightRun % 2 ? 'row-changed-a' : 'row-changed-b'} 1.8s ease-out`,
                      }
            }
        >
            <div className="flex min-w-0 flex-1 items-center gap-3">
                <span
                    className="w-6 shrink-0 text-right text-sm text-muted-foreground tabular-nums"
                    aria-hidden="true"
                >
                    {rank}
                </span>
                <div className="min-w-0 flex-1 space-y-1">
                    {/* The badge sits on the title line so every vote bar gets the same track width. */}
                    <div className="flex min-w-0 items-center gap-2">
                        <p className="truncate font-medium" title={game.name}>
                            {game.name}
                        </p>
                        {game.voted && (
                            <Badge variant="secondary">
                                <Check /> Your vote
                            </Badge>
                        )}
                    </div>
                    {/* On phones, end where the vote button ends: leave room for the remove button (size-9) and its gap. */}
                    <MeepleVotes
                        votes={game.votes}
                        maxVotes={maxVotes}
                        className="mr-11 sm:mr-0"
                    />
                </div>
            </div>

            <div className="flex items-center gap-2 pl-9 sm:pl-0">
                {game.voted ? (
                    <Form
                        {...GameVoteController.destroy.form(game.id)}
                        options={{ preserveScroll: true }}
                        onStart={() => reportPending(true)}
                        onFinish={() => reportPending(false)}
                        className="flex-1 sm:w-36 sm:flex-none"
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                                className="w-full"
                                data-test="remove-vote-button"
                            >
                                {processing ? <Spinner /> : <Undo2 />}
                                Remove vote
                            </Button>
                        )}
                    </Form>
                ) : (
                    <Form
                        {...GameVoteController.store.form(game.id)}
                        options={{ preserveScroll: true }}
                        onStart={() => reportPending(true)}
                        onFinish={() => reportPending(false)}
                        className="flex-1 sm:w-36 sm:flex-none"
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                disabled={!canVote || processing}
                                title={
                                    canVote
                                        ? undefined
                                        : 'You have used today’s action'
                                }
                                className="w-full"
                                data-test="vote-button"
                            >
                                {processing ? <Spinner /> : <ThumbsUp />}
                                Vote
                            </Button>
                        )}
                    </Form>
                )}
                <RemoveGameDialog game={game} onPendingChange={reportPending} />
            </div>
        </li>
    );
}

/**
 * The server sends new objects for every game after each request, so compare the game's
 * fields rather than its identity: only rows whose data, rank or highlight changed re-render.
 */
export default memo(
    GameListItem,
    (previous, next) =>
        previous.game.id === next.game.id &&
        previous.game.name === next.game.name &&
        previous.game.votes === next.game.votes &&
        previous.game.voted === next.game.voted &&
        previous.rank === next.rank &&
        previous.maxVotes === next.maxVotes &&
        previous.canVote === next.canVote &&
        previous.highlightRun === next.highlightRun &&
        previous.ref === next.ref &&
        previous.onPendingChange === next.onPendingChange,
);
