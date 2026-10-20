<?php

namespace Database\Factories;

use App\Enums\DailyActionType;
use App\Models\DailyAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyAction>
 */
class DailyActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action_date' => now()->toDateString(),
            'type' => DailyActionType::Vote,
            'game_id' => fake()->unique()->numberBetween(1, 100000),
        ];
    }

    /**
     * Indicate that the action was adding a game.
     */
    public function addedGame(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DailyActionType::AddGame,
        ]);
    }
}
