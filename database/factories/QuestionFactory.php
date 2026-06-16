<?php

namespace Database\Factories;

use App\Models\KnowledgeUnit;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'knowledge_unit_id' => KnowledgeUnit::factory(),
            'kind' => 'mc',
            'prompt' => rtrim(fake()->sentence(6), '.').'?',
            'options_json' => [
                ['text' => 'Richtig', 'correct' => true],
                ['text' => 'Falsch A', 'correct' => false],
                ['text' => 'Falsch B', 'correct' => false],
                ['text' => 'Falsch C', 'correct' => false],
            ],
            'correct_answer' => 'Richtig',
        ];
    }

    public function mc(): static
    {
        return $this->state(fn (array $attributes) => ['kind' => 'mc']);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => 'free',
            'options_json' => null,
            'correct_answer' => fake()->sentence(),
        ]);
    }
}
