import { CalendarCheck, Sparkles } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import type { DailyAllowance, Game } from '@/types';

function formatResetTime(resetsAt: string, timezone: string): string {
    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
        timeZone: timezone,
        timeZoneName: 'short',
    }).format(new Date(resetsAt));
}

export default function DailyAllowanceNotice({
    allowance,
    games,
    timezone,
}: {
    allowance: DailyAllowance;
    games: Game[] | null;
    timezone: string;
}) {
    if (allowance.available) {
        return (
            <Alert>
                <Sparkles />
                <AlertTitle>You have one action today</AlertTitle>
                <AlertDescription>
                    Vote for a game you want, or suggest a new one. You can do
                    one or the other each day.
                </AlertDescription>
            </Alert>
        );
    }

    const gameName = games?.find((game) => game.id === allowance.game_id)?.name;
    const spentOn =
        allowance.used_for === 'vote'
            ? gameName
                ? `You voted for ${gameName}`
                : 'You voted'
            : gameName
              ? `You added ${gameName}`
              : 'You added a game';

    return (
        <Alert>
            <CalendarCheck />
            <AlertTitle>{spentOn} today</AlertTitle>
            <AlertDescription>
                Your next vote or new game unlocks at midnight (
                {formatResetTime(allowance.resets_at, timezone)}).
                {allowance.used_for === 'vote' &&
                    ' Changed your mind? Remove today’s vote to get your action back.'}
            </AlertDescription>
        </Alert>
    );
}
