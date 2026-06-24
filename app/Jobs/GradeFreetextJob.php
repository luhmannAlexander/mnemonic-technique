<?php

namespace App\Jobs;

use App\Contracts\LLMServiceInterface;
use App\Exceptions\LLMException;
use App\Models\Attempt;
use App\Models\KnowledgeUnit;
use App\Models\Question;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Grades a free-text answer asynchronously (ImplementationPlan §3.2). The user
 * never waits on this: the attempt is stored as `pending`, this job fills in the
 * result, updates the session tally, and (for due sessions) reschedules the card.
 *
 * On LLM failure the answer is conservatively marked `wrong` [PRD K-6] rather
 * than left pending forever.
 */
class GradeFreetextJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public int $attemptId) {}

    public function handle(LLMServiceInterface $llm): void
    {
        $attempt = Attempt::find($this->attemptId);

        if ($attempt === null || $attempt->result !== 'pending') {
            return;
        }

        try {
            $unit = KnowledgeUnit::findOrFail($attempt->knowledge_unit_id);
            $question = Question::findOrFail($attempt->question_id);
            $result = $llm->gradeFreetextAnswer($unit->toArray(), $question->prompt, $attempt->given_answer);

            $attempt->update([
                'result' => $result['result'],
                'ai_feedback' => $result['feedback'],
                'ai_graded_at' => now(),
            ]);
        } catch (LLMException) {
            $attempt->update([
                'result' => 'wrong',
                'ai_feedback' => null,
                'ai_graded_at' => now(),
            ]);
        } finally {
            $attempt->refresh();
            $this->finaliseSessionTally($attempt);

            // Voluntary practice must not affect the spaced-repetition queue
            // (AppFlow §2.11, M3 gate item 7).
            if ($attempt->session?->session_type === 'due') {
                PrioritiseReviewJob::dispatch($attempt->knowledge_unit_id, $attempt->user_id, $attempt->result);
            }
        }
    }

    /** Decrement the pending counter and bump the graded-result bucket, atomically (BackendSchema O-6). */
    private function finaliseSessionTally(Attempt $attempt): void
    {
        $column = match ($attempt->result) {
            'correct' => 'questions_correct',
            'partial' => 'questions_partial',
            default => 'questions_wrong',
        };

        DB::update(
            "UPDATE session_logs SET questions_pending = GREATEST(questions_pending - 1, 0), {$column} = {$column} + 1 WHERE id = ?",
            [$attempt->session_id],
        );
    }
}
