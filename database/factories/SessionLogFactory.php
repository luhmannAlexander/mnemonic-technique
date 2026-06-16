<?php

namespace Database\Factories;

use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionLog>
 */
class SessionLogFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => null, // global session by default
            'session_type' => 'due',
            'status' => 'active',
            'questions_total' => 10,
            'questions_answered' => 0,
            'questions_correct' => 0,
            'questions_partial' => 0,
            'questions_wrong' => 0,
            'questions_pending' => 0,
            'current_question_index' => 0,
            'started_at' => now(),
            'finished_at' => null,
        ];
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'finished',
            'finished_at' => now(),
        ]);
    }

    public function interrupted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'interrupted',
        ]);
    }
}
