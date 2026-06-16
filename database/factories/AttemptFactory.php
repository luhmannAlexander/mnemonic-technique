<?php

namespace Database\Factories;

use App\Models\Attempt;
use App\Models\SessionLog;
use App\Models\SessionQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attempt>
 */
class AttemptFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'session_question_id' => SessionQuestion::factory(),
            // Derive the rest of the chain from the session_question for consistency.
            'session_id' => fn (array $attributes) => SessionQuestion::find($attributes['session_question_id'])?->session_id,
            'question_id' => fn (array $attributes) => SessionQuestion::find($attributes['session_question_id'])?->question_id,
            'knowledge_unit_id' => fn (array $attributes) => SessionQuestion::find($attributes['session_question_id'])?->knowledge_unit_id,
            'user_id' => fn (array $attributes) => SessionLog::find($attributes['session_id'])?->user_id,
            'project_id' => null,
            'topic_tag' => ucfirst(fake()->word()),
            'kind' => 'mc',
            'given_answer' => fake()->sentence(),
            'result' => 'correct',
            'ai_feedback' => null,
            'ai_graded_at' => null,
            'attempted_at' => now(),
        ];
    }
}
