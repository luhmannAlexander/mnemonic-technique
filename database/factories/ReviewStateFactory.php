<?php

namespace Database\Factories;

use App\Models\KnowledgeUnit;
use App\Models\ReviewState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewState>
 */
class ReviewStateFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'knowledge_unit_id' => KnowledgeUnit::factory(),
            'user_id' => fn (array $attributes) => KnowledgeUnit::find($attributes['knowledge_unit_id'])?->user_id,
            'due_at' => now(),
            'priority' => 0,
            'interval_days' => 1,
            'last_result' => null,
            'last_attempted_at' => null,
            'attempt_count' => 0,
            'correct_count' => 0,
        ];
    }

    public function due(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_at' => now()->subDay(),
        ]);
    }
}
