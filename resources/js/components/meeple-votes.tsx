import Meeple from '@/components/meeple';
import { cn } from '@/lib/utils';

/** Classic board game piece colours, cycled so the bar looks like a row of meeples. */
const colors = [
    'text-red-500',
    'text-blue-500',
    'text-yellow-400',
    'text-green-600',
    'text-orange-500',
    'text-purple-500',
    'text-neutral-800 dark:text-neutral-200',
];

/** About as many meeples as the widest bar can show; any that don't fit stay hidden. */
const maxRendered = 20;

/**
 * A bar chart row: the bar's length is this game's share of the top game's votes, filled with
 * meeples (never more than the votes), with the exact total on the right.
 */
export default function MeepleVotes({
    votes,
    maxVotes,
    className,
}: {
    votes: number;
    maxVotes: number;
    className?: string;
}) {
    const label =
        votes === 0
            ? 'No votes yet'
            : votes === 1
              ? '1 vote'
              : `${votes} votes`;
    const share = maxVotes > 0 ? Math.min(100, (votes / maxVotes) * 100) : 0;

    return (
        <div
            role="img"
            aria-label={label}
            title={label}
            className={cn(
                'flex h-8 items-center gap-2 overflow-hidden rounded-md bg-muted/60 pr-2',
                className,
            )}
        >
            <div className="h-full min-w-0 flex-1">
                {votes === 0 ? (
                    <span className="flex h-full items-center px-2 text-sm text-muted-foreground">
                        No votes yet
                    </span>
                ) : (
                    // One row tall: meeples that don't fit wrap onto a hidden second row,
                    // so the fill only ever shows whole meeples.
                    <div
                        className="vote-bar-fill flex h-full flex-wrap gap-x-0.5 overflow-hidden rounded-md px-1 transition-[width] duration-500 ease-out"
                        style={{ width: `max(1.75rem, ${share}%)` }}
                    >
                        {Array.from(
                            { length: Math.min(votes, maxRendered) },
                            (_, index) => (
                                <span
                                    key={index}
                                    className="flex h-full shrink-0 items-center"
                                >
                                    <Meeple
                                        className={cn(
                                            'size-5 drop-shadow-sm sm:size-6',
                                            colors[index % colors.length],
                                        )}
                                    />
                                </span>
                            ),
                        )}
                    </div>
                )}
            </div>
            {votes > 0 && (
                <span className="shrink-0 text-sm font-semibold tabular-nums">
                    {votes}
                </span>
            )}
        </div>
    );
}
