<?php

namespace App\Jobs;

use App\Contracts\LLMServiceInterface;
use App\Exceptions\LLMException;
use App\Models\KnowledgeUnit;
use App\Models\Question;
use App\Observers\KnowledgeUnitObserver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Generates one MC + one free-text question for an approved knowledge unit.
 *
 * Dispatched by {@see KnowledgeUnitObserver} when a unit becomes
 * `approved` (ImplementationPlan §2.5). Questions are upserted on the
 * (knowledge_unit_id, kind) unique key so regeneration overwrites in place.
 */
class GenerateQuestionsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public int $knowledgeUnitId) {}

    public function handle(LLMServiceInterface $llm): void
    {
        $unit = KnowledgeUnit::find($this->knowledgeUnitId);

        if ($unit === null || $unit->unit_status !== 'approved') {
            return;
        }

        // Manual edits may have invalidated AI-generated questions — leave any
        // existing ones untouched rather than overwriting the user's intent.
        if ($unit->manually_edited) {
            return;
        }

        $result = $llm->generateQuestions($unit->toArray());

        foreach (['mc', 'free'] as $kind) {
            if (! isset($result[$kind])) {
                continue;
            }

            $this->upsertQuestion($unit, $kind, $result[$kind]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertQuestion(KnowledgeUnit $unit, string $kind, array $payload): void
    {
        if (! isset($payload['prompt'], $payload['correct_answer'])) {
            throw new LLMException("Frage vom Typ '{$kind}' ist unvollständig.");
        }

        Question::updateOrCreate(
            ['knowledge_unit_id' => $unit->id, 'kind' => $kind],
            [
                'prompt' => $payload['prompt'],
                'options_json' => $kind === 'mc' ? ($payload['options'] ?? null) : null,
                'correct_answer' => $payload['correct_answer'],
            ],
        );
    }
}
