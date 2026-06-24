<?php

namespace App\Jobs;

use App\Contracts\LLMServiceInterface;
use App\Exceptions\LLMException;
use App\Models\Attempt;
use App\Models\ReviewState;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reschedules a card after an answer (ImplementationPlan §3.3). Asks the LLM for
 * a due date + priority based on recent history; if the model is unavailable it
 * falls back to a deterministic interval algorithm (BackendSchema D-5) so the
 * spaced-repetition queue keeps working without AI [PRD K-6].
 *
 * Only dispatched for `due` sessions — voluntary practice never reschedules.
 */
class PrioritiseReviewJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(
        public int $unitId,
        public int $userId,
        public string $lastResult,
    ) {}

    public function handle(LLMServiceInterface $llm): void
    {
        $state = ReviewState::where('knowledge_unit_id', $this->unitId)
            ->where('user_id', $this->userId)
            ->first();

        if ($state === null) {
            return;
        }

        try {
            $history = Attempt::where('knowledge_unit_id', $this->unitId)
                ->where('user_id', $this->userId)
                ->orderByDesc('attempted_at')
                ->limit(10)
                ->get(['result', 'attempted_at'])
                ->toArray();

            $result = $llm->prioritiseReview([['id' => $this->unitId, 'history' => $history]]);
            $ranked = $result[0] ?? throw new LLMException('Leere Priorisierungs-Antwort.');

            $this->persist([
                'due_at' => Carbon::parse($ranked['due_at']),
                'priority' => $ranked['priority'],
            ]);
        } catch (LLMException) {
            // Deterministic fallback: stretch on success, reset on failure.
            $newInterval = match ($this->lastResult) {
                'correct' => min($state->interval_days * 2, 14),
                'partial' => $state->interval_days,
                default => 1,
            };

            $this->persist([
                'due_at' => now()->addDays($newInterval),
                'interval_days' => $newInterval,
            ]);
        }
    }

    /**
     * Write via the query builder so DB::raw increments bypass attribute casts.
     * `last_result` / `last_attempted_at` / the attempt counters are common to
     * both the AI and fallback paths.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function persist(array $attributes): void
    {
        ReviewState::where('knowledge_unit_id', $this->unitId)
            ->where('user_id', $this->userId)
            ->update([
                ...$attributes,
                'last_result' => $this->lastResult,
                'last_attempted_at' => now(),
                'attempt_count' => DB::raw('attempt_count + 1'),
                'correct_count' => $this->lastResult === 'correct'
                    ? DB::raw('correct_count + 1')
                    : DB::raw('correct_count'),
            ]);
    }
}
