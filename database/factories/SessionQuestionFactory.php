<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\SessionLog;
use App\Models\SessionQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionQuestion>
 */
class SessionQuestionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'session_id' => SessionLog::factory(),
            'question_id' => Question::factory(),
            'knowledge_unit_id' => fn (array $attributes) => Question::find($attributes['question_id'])?->knowledge_unit_id,
            'position' => 1,
            'presented_at' => null,
        ];
    }
}
