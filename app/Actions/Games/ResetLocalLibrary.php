<?php

namespace App\Actions\Games;

use App\Models\Vote;
use App\Services\DailyAllowance;
use Illuminate\Support\Facades\DB;

class ResetLocalLibrary
{
    public function __construct(private readonly DailyAllowance $allowance) {}

    /**
     * Discard local data that belonged to a game library which no longer applies.
     *
     * Each API key has its own library whose game ids start again at 1, so votes recorded
     * against another key would point at the wrong games. Today's actions on those games
     * are refunded; earlier days stay recorded.
     */
    public function handle(): void
    {
        DB::transaction(function () {
            $this->allowance->refundEveryoneToday();
            Vote::query()->delete();
        });
    }
}
