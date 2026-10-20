<?php

namespace App\Models;

use App\Enums\DailyActionType;
use Carbon\CarbonImmutable;
use Database\Factories\DailyActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The single action (a vote or a new game) a user spent on one local calendar day.
 *
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $action_date
 * @property DailyActionType $type
 * @property int|null $game_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'action_date', 'type', 'game_id'])]
class DailyAction extends Model
{
    /** @use HasFactory<DailyActionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action_date' => 'date',
            'type' => DailyActionType::class,
        ];
    }

    /**
     * Get the user who took the action.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
