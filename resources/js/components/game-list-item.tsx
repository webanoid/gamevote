import { Form } from '@inertiajs/react';
import { Check, ThumbsUp, Trash2, Undo2 } from 'lucide-react';
import GameController from '@/actions/App/Http/Controllers/GameController';
import GameVoteController from '@/actions/App/Http/Controllers/GameVoteController';
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

function RemoveGameDialog({ game }: { game: Game }) {
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

export default function GameListItem({
    game,
    rank,
    canVote,
}: {
    game: Game;
    rank: number;
    canVote: boolean;
}) {
    return (
        <li
            className={cn(
                'flex flex-col gap-3 p-4 sm:flex-row sm:items-center',
                game.voted && 'bg-primary/[0.03] dark:bg-primary/[0.06]',
            )}
        >
            <div className="flex min-w-0 flex-1 items-center gap-3">
                <span
                    className="w-6 shrink-0 text-right text-sm text-muted-foreground tabular-nums"
                    aria-hidden="true"
                >
                    {rank}
                </span>
                <div className="min-w-0">
                    <p className="truncate font-medium" title={game.name}>
                        {game.name}
                    </p>
                    <p className="text-sm text-muted-foreground tabular-nums">
                        {votesLabel(game.votes)}
                    </p>
                </div>
                {game.voted && (
                    <Badge variant="secondary" className="ml-auto sm:ml-0">
                        <Check /> Your vote
                    </Badge>
                )}
            </div>

            <div className="flex items-center gap-2 pl-9 sm:pl-0">
                {game.voted ? (
                    <Form
                        {...GameVoteController.destroy.form(game.id)}
                        options={{ preserveScroll: true }}
                        className="flex-1 sm:flex-none"
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
                        className="flex-1 sm:flex-none"
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
                <RemoveGameDialog game={game} />
            </div>
        </li>
    );
}
