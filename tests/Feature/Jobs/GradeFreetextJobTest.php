<?php

use App\Contracts\LLMServiceInterface;
use App\Exceptions\LLMException;
use App\Jobs\GenerateQuestionsJob;
use App\Jobs\GradeFreetextJob;
use App\Jobs\PrioritiseReviewJob;
use App\Models\Attempt;
use App\Models\KnowledgeUnit;
use App\Models\Project;
use App\Models\Question;
use App\Models\SessionLog;
use App\Models\SessionQuestion;
use App\Models\User;
use App\Services\FakeLLMService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Fake the downstream job (asserted) and the observer's question generator
    // (so it doesn't auto-create a colliding free question); GradeFreetextJob
    // itself still runs for real via dispatchSync.
    Queue::fake([PrioritiseReviewJob::class, GenerateQuestionsJob::class]);
    $this->user = User::factory()->create();
});

/** A pending free-text attempt wired to a session with one pending grade. */
function pendingFreeAttempt(User $user, string $sessionType = 'due'): Attempt
{
    $session = SessionLog::factory()->for($user)->create([
        'session_type' => $sessionType, 'questions_pending' => 1,
    ]);
    $project = Project::factory()->for($user)->create();
    $unit = KnowledgeUnit::factory()->approved()->create([
        'project_id' => $project->id, 'user_id' => $user->id, 'document_id' => null,
    ]);
    $question = Question::factory()->free()->for($unit)->create();
    $sq = SessionQuestion::factory()->create([
        'session_id' => $session->id, 'question_id' => $question->id, 'knowledge_unit_id' => $unit->id,
    ]);

    return Attempt::factory()->create([
        'session_question_id' => $sq->id,
        'session_id' => $session->id,
        'question_id' => $question->id,
        'knowledge_unit_id' => $unit->id,
        'user_id' => $user->id,
        'kind' => 'free',
        'given_answer' => 'Meine Antwort.',
        'result' => 'pending',
    ]);
}

it('grades a pending free-text attempt and tallies the session', function () {
    $attempt = pendingFreeAttempt($this->user);

    GradeFreetextJob::dispatchSync($attempt->id);

    $attempt->refresh();
    $session = $attempt->session;

    expect($attempt->result)->toBe('correct')
        ->and($attempt->ai_feedback)->toBe('Korrekt.')
        ->and($attempt->ai_graded_at)->not->toBeNull()
        ->and($session->questions_pending)->toBe(0)
        ->and($session->questions_correct)->toBe(1);

    Queue::assertPushed(PrioritiseReviewJob::class);
});

it('marks the attempt wrong when grading fails', function () {
    $attempt = pendingFreeAttempt($this->user);

    app()->bind(LLMServiceInterface::class, fn () => new class extends FakeLLMService
    {
        public function gradeFreetextAnswer(array $unit, string $question, string $answer): array
        {
            throw new LLMException('Modell nicht erreichbar.');
        }
    });

    GradeFreetextJob::dispatchSync($attempt->id);

    $attempt->refresh();

    expect($attempt->result)->toBe('wrong')
        ->and($attempt->ai_feedback)->toBeNull()
        ->and($attempt->session->questions_wrong)->toBe(1)
        ->and($attempt->session->questions_pending)->toBe(0);

    Queue::assertPushed(PrioritiseReviewJob::class);
});

it('does not reschedule for a voluntary session', function () {
    $attempt = pendingFreeAttempt($this->user, sessionType: 'voluntary');

    GradeFreetextJob::dispatchSync($attempt->id);

    expect($attempt->fresh()->result)->toBe('correct');
    Queue::assertNotPushed(PrioritiseReviewJob::class);
});

it('ignores an attempt that was already graded', function () {
    $attempt = pendingFreeAttempt($this->user);
    $attempt->update(['result' => 'correct']);

    GradeFreetextJob::dispatchSync($attempt->id);

    // questions_pending stays at its original value (the job returned early).
    expect($attempt->session->fresh()->questions_pending)->toBe(1);
    Queue::assertNotPushed(PrioritiseReviewJob::class);
});
