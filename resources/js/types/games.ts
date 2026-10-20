export type Game = {
    id: number;
    name: string;
    votes: number;
    voted: boolean;
};

export type DailyAllowance = {
    enforced: boolean;
    available: boolean;
    used_for: 'vote' | 'add_game' | null;
    game_id: number | null;
    resets_at: string;
};
