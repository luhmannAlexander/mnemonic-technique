<?php

namespace App\Services;

use App\Exceptions\NoCardsAvailableException;
use App\Models\KnowledgeUnit;
use App\Models\Question;
use App\Models\ReviewState;
use App\Models\SessionLog;
use App\Models\SessionQuestion;
use App\Models\UserSetting;
use Illuminate\Support\Collection;

/**
 * Builds practice sessions: selects eligible units, freezes an ordered question
 * queue (so a session can be resumed), and enforces the one-open-session-per-slot
 * rule (BackendSchema D-1). A "slot" is (user, project_id) with project_id NULL
 * meaning the global slot.
 */
class SessionService
{
    /**
     * Start (or resume) a session for a slot.
     *
     * @param  string  $type  'due' (spaced-repetition queue) | 'voluntary' (extra practice)
     *
     * @throws NoCardsAvailableException
     */
    public function start(int $userId, ?int $projectId, string $type = 'due'): SessionLog
    {
        // One open session per slot: hand back the existing one for the resume flow.
        $existing = SessionLog::where('user_id', $userId)
            ->where('project_id', $projectId)
            ->whereIn('status', ['active', 'interrupted'])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $sessionLength = UserSetting::where('user_id', $userId)->value('session_length') ?? 10;

        $units = $this->selectUnits($userId, $projectId, $type, $sessionLength);

        if ($units->isEmpty()) {
            throw new NoCardsAvailableException;
        }

        $session = SessionLog::create([
            'user_id' => $userId,
            'project_id' => $projectId,
            'session_type' => $type,
            'status' => 'active',
            'questions_total' => $units->count(),
        ]);

        $queued = $this->buildQueue($session, $units);

        // Selected units may have no generated questions yet — without a queue the
        // session is empty, so roll it back and report no cards.
        if ($queued === 0) {
            $session->delete();

            throw new NoCardsAvailableException;
        }

        return $session;
    }

    /**
     * @return Collection<int, KnowledgeUnit>
     */
    private function selectUnits(int $userId, ?int $projectId, string $type, int $limit): Collection
    {
        $query = ReviewState::query()
            ->where('review_states.user_id', $userId)
            ->whereUnitHasQuestions() // question-less units can't be queued (buildQueue skips them)
            ->join('knowledge_units', 'knowledge_units.id', '=', 'review_states.knowledge_unit_id')
            ->whereNull('knowledge_units.deleted_at')
            ->where('knowledge_units.unit_status', 'approved');

        if ($projectId !== null) {
            $query->where('knowledge_units.project_id', $projectId);
        }

        if ($type === 'due') {
            $query->where('review_states.due_at', '<=', now())
                ->orderByDesc('review_states.priority')
                ->orderBy('review_states.due_at');
        } else {
            // voluntary: longest-unexercised first (BackendSchema D-2)
            $query->orderByRaw('review_states.last_attempted_at IS NOT NULL, review_states.last_attempted_at asc')
                ->orderBy('review_states.due_at');
        }

        /** @var Collection<int, KnowledgeUnit> $units */
        $units = $query->limit($limit)->select('knowledge_units.*')->get();

        return $units;
    }

    /**
     * Freeze the ordered question queue. Prefers MC (works without the model);
     * skips units that have no questions yet. Returns the number of rows queued.
     *
     * @param  Collection<int, KnowledgeUnit>  $units
     */
    private function buildQueue(SessionLog $session, Collection $units): int
    {
        $questions = Question::whereIn('knowledge_unit_id', $units->pluck('id'))
            ->get()
            ->groupBy('knowledge_unit_id');

        $rows = [];
        $position = 0;

        foreach ($units as $unit) {
            $unitQuestions = $questions->get($unit->id);

            if ($unitQuestions === null) {
                continue;
            }

            $question = $unitQuestions->firstWhere('kind', 'mc') ?? $unitQuestions->first();

            $rows[] = [
                'session_id' => $session->id,
                'question_id' => $question->id,
                'knowledge_unit_id' => $unit->id,
                'position' => ++$position,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            SessionQuestion::insert($rows);
        }

        $session->update(['questions_total' => count($rows)]);

        return count($rows);
    }
}
