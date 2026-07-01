<?php

use App\Jobs\GenerateQuestionsJob;
use App\Jobs\GradeFreetextJob;
use App\Jobs\PrioritiseReviewJob;
use App\Models\Attempt;
use App\Models\KnowledgeUnit;
use App\Models\Project;
use App\Models\Question;
use App\Models\ReviewState;
use App\Models\SessionLog;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Capture the observer's GenerateQuestionsJob (so it doesn't auto-create
    // questions) plus the async grading/rescheduling jobs we assert on.
    Queue::fake([GenerateQuestionsJob::class, GradeFreetextJob::class, PrioritiseReviewJob::class]);
    $this->user = User::factory()->create();
});

/**
 * Build a real, queued session for the component to drive.
 *
 * @param  'mc'|'free'  $kind
 */
function practiceSession(User $user, string $type = 'due', int $count = 1, string $kind = 'mc'): SessionLog
{
    $project = Project::factory()->for($user)->create();

    collect(range(1, $count))->each(function () use ($project, $user, $kind) {
        $unit = KnowledgeUnit::factory()->approved()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'document_id' => null,
            'technique_material' => 'Stell dir eine Eselsbrücke vor.',
        ]);
        ReviewState::where('knowledge_unit_id', $unit->id)->update(['due_at' => now()->subDay()]);
        $kind === 'mc'
            ? Question::factory()->mc()->for($unit)->create()
            : Question::factory()->free()->for($unit)->create();
    });

    return app(SessionService::class)->start($user->id, $project->id, $type);
}

it('skips a question-less due card so it cannot shadow a practisable one at the session limit', function () {
    $project = Project::factory()->for($this->user)->create();
    UserSetting::updateOrCreate(['user_id' => $this->user->id], ['session_length' => 1]);

    // Question-less card sorts FIRST (highest priority) — must be skipped, not fill the only slot.
    $bad = KnowledgeUnit::factory()->approved()->create([
        'project_id' => $project->id, 'user_id' => $this->user->id, 'document_id' => null,
    ]);
    ReviewState::where('knowledge_unit_id', $bad->id)->update(['due_at' => now()->subDays(2), 'priority' => 99]);

    // Practisable card (has a question), lower priority.
    $good = KnowledgeUnit::factory()->approved()->create([
        'project_id' => $project->id, 'user_id' => $this->user->id, 'document_id' => null,
    ]);
    ReviewState::where('knowledge_unit_id', $good->id)->update(['due_at' => now()->subDay(), 'priority' => 1]);
    Question::factory()->mc()->for($good)->create();

    $session = app(SessionService::class)->start($this->user->id, $project->id, 'due');

    expect($session->questions_total)->toBe(1)
        ->and($session->sessionQuestions()->first()->knowledge_unit_id)->toBe($good->id);
});

it('shows the first question in the focus layout', function () {
    $session = practiceSession($this->user);
    $prompt = $session->sessionQuestions()->first()->question->prompt;

    Livewire::actingAs($this->user)
        ->test('practice.practice-session', ['session' => $session])
        ->assertSet('answered', false)
        ->assertSee($prompt);
});

it('records and tallies a correct MC answer and reschedules a due card', function () {
    $session = practiceSession($this->user);

    Livewire::actingAs($this->user)
        ->test('practice.practice-session', ['session' => $session])
        ->call('answerMC', 0) // option 0 is correct in the factory
        ->assertSet('answered', true)
        ->assertSet('feedbackResult', 'correct');

    $attempt = Attempt::where('session_id', $session->id)->first();
    expect($attempt->result)->toBe('correct')
        ->and($attempt->kind)->toBe('mc');

    $session->refresh();
    expect($session->questions_correct)->toBe(1)
        ->and($session->questions_answered)->toBe(1);

    Queue::assertPushed(PrioritiseReviewJob::class);
});

it('stores a wrong MC answer with result=wrong', function () {
    $session = practiceSession($this->user);

    Livewire::actingAs($this->user)
        ->test('practice.practice-session', ['session' => $session])
        ->call('answerMC', 1) // a distractor
        ->assertSet('feedbackResult', 'wrong');

    expect(Attempt::where('session_id', $session->id)->first()->result)->toBe('wrong')
        ->and($session->fresh()->questions_wrong)->toBe(1);
});

it('submits a free-text answer as pending and dispatches grading without blocking', function () {
    $session = practiceSession($this->user, kind: 'free');

    Livewire::actingAs($this->user)
        ->test('practice.practice-session', ['session' => $session])
        ->set('freeAnswer', 'Meine ausführliche Antwort.')
        ->call('answerFree')
        ->assertSet('answered', true)
        ->assertSet('feedbackResult', null);

    $attempt = Attempt::where('session_id', $session->id)->first();
    expect($attempt->result)->toBe('pending')
        ->and($attempt->kind)->toBe('free');

    expect($session->fresh()->questions_pending)->toBe(1);
    Queue::assertPushed(GradeFreetextJob::class);
    Queue::assertNotPushed(PrioritiseReviewJob::class); // grading job reschedules, not the component
});

it('ignores an empty free-text submission', function () {
    $session = practiceSession($this->user, kind: 'free');

    Livewire::actingAs($this->user)
        ->test('practice.practice-session', ['session' => $session])
        ->set('freeAnswer', '   ')
        ->call('answerFree')
        ->assertSet('answered', false);

    expect(Attempt::where('session_id', $session->id)->count())->toBe(0);
});

it('does not reschedule on an MC answer in a voluntary session', function () {
    $session = practiceSession($this->user, type: 'voluntary');

    Livewire::actingAs($this->user)
        ->test('practice.practice-session', ['session' => $session])
        ->call('answerMC', 0);

    Queue::assertNotPushed(PrioritiseReviewJob::class);
});

it('advances to the next question', function () {
    $session = practiceSession($this->user, count: 2);

    $component = Livewire::actingAs($this->user)
        ->test('practice.practice-session', ['session' => $session])
        ->call('answerMC', 0)
        ->call('next')
        ->assertSet('answered', false);

    expect($component->get('currentQuestion')->position)->toBe(2)
        ->and($session->fresh()->current_question_index)->toBe(1);
});

it('finishes and redirects to the summary after the last question', function () {
    $session = practiceSession($this->user, count: 1);

    Livewire::actingAs($this->user)
        ->test('practice.practice-session', ['session' => $session])
        ->call('answerMC', 0)
        ->call('next')
        ->assertRedirect(route('practice.summary', $session));

    expect($session->fresh()->status)->toBe('finished')
        ->and($session->fresh()->finished_at)->not->toBeNull();
});

it('interrupts a project session and redirects back to the project', function () {
    $session = practiceSession($this->user, count: 3);

    Livewire::actingAs($this->user)
        ->test('practice.practice-session', ['session' => $session])
        ->call('interrupt')
        ->assertRedirect(route('projects.show', $session->project_id));

    expect($session->fresh()->status)->toBe('interrupted');
});

it('interrupts a global session and redirects to the dashboard', function () {
    // A global (project_id = null) session built directly so interrupt returns home.
    $project = Project::factory()->for($this->user)->create();
    $unit = KnowledgeUnit::factory()->approved()->create([
        'project_id' => $project->id, 'user_id' => $this->user->id, 'document_id' => null,
    ]);
    ReviewState::where('knowledge_unit_id', $unit->id)->update(['due_at' => now()->subDay()]);
    Question::factory()->mc()->for($unit)->create();
    $session = app(SessionService::class)->start($this->user->id, null, 'due');

    Livewire::actingAs($this->user)
        ->test('practice.practice-session', ['session' => $session])
        ->call('interrupt')
        ->assertRedirect(route('dashboard'));

    expect($session->fresh()->status)->toBe('interrupted');
});

it('resumes an interrupted session at the saved position', function () {
    $session = practiceSession($this->user, count: 3);
    $session->update(['status' => 'interrupted', 'current_question_index' => 2]);

    $component = Livewire::actingAs($this->user)
        ->test('practice.practice-session', ['session' => $session]);

    expect($session->fresh()->status)->toBe('active'); // reactivated on resume
    expect($component->get('currentQuestion')->position)->toBe(3);
});

it('returns 404 for another user\'s session', function () {
    $session = practiceSession($this->user);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('practice.session', $session))
        ->assertNotFound();
});

it('renders the focus screen for the owner', function () {
    $session = practiceSession($this->user);

    $this->actingAs($this->user)
        ->get(route('practice.session', $session))
        ->assertOk()
        ->assertSee('Frage 1 von 1');
});
