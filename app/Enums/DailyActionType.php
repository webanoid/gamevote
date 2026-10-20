<?php

namespace App\Enums;

enum DailyActionType: string
{
    case Vote = 'vote';
    case AddGame = 'add_game';
}
