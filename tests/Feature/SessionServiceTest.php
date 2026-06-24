<?php

use App\Exceptions\NoCardsAvailableException;
use App\Models\KnowledgeUnit;
use App\Models\Project;
use App\Models\Question;
use App\Models\ReviewState;
use App\Models\SessionLog;
use App\Models\SessionQuestion;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake(); // capture the observer's GenerateQuestionsJob so we control questions
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();
    $this->service = app(SessionService::class);
});

/** Approved unit that is due now; optionally with an MC question. */
function dueUnit(Project $project, User $user, bool $withQuestion = true, ?string $dueAt = '-1 day'): KnowledgeUnit
{
    $unit = KnowledgeUnit::factory()->approved()->create([
        'project_id' => $project->id, 'user_id' => $user->id, 'document_id' => null,
    ]);

    ReviewState::where('knowledge_unit_id', $unit->id)->update(['due_at' => now()->modify($dueAt)]);

    if ($withQuestion) {
        Question::factory()->mc()->for($unit)->create();
    }

    return $unit;
}

it('builds a session with one question per due unit', function () {
    collect(range(1, 5))->each(fn () => dueUnit($this->project, $this->user));

    $session = $this->service->start($this->user->id, $this->project->id, 'due');

    expect($session->status)->toBe('active')
        ->and($session->session_type)->toBe('due')
        ->and($session->questions_total)->toBe(5)
        ->and(SessionQuestion::where('session_id', $session->id)->count())->toBe(5);
});

it('respects the configured session length', function () {
    collect(range(1, 8))->each(fn () => dueUnit($this->project, $this->user));
    UserSetting::where('user_id', $this->user->id)->update(['session_length' => 5]);

    $session = $this->service->start($this->user->id, $this->project->id, 'due');

    expect(SessionQuestion::where('session_id', $session->id)->count())->toBe(5);
});

it('returns the existing open session instead of starting a new one', function () {
    $existing = SessionLog::factory()->for($this->user)->create([
        'project_id' => $this->project->id, 'status' => 'active',
    ]);

    $session = $this->service->start($this->user->id, $this->project->id, 'due');

    expect($session->id)->toBe($existing->id)
        ->and(SessionLog::count())->toBe(1);
});

it('resumes an interrupted session for the same slot', function () {
    $existing = SessionLog::factory()->for($this->user)->interrupted()->create(['project_id' => $this->project->id]);

    $session = $this->service->start($this->user->id, $this->project->id, 'due');

    expect($session->id)->toBe($existing->id);
});

it('throws when no card is due', function () {
    dueUnit($this->project, $this->user, dueAt: '+3 days'); // scheduled in the future

    expect(fn () => $this->service->start($this->user->id, $this->project->id, 'due'))
        ->toThrow(NoCardsAvailableException::class);
});

it('skips due units that have no questions yet', function () {
    dueUnit($this->project, $this->user, withQuestion: true);
    dueUnit($this->project, $this->user, withQuestion: true);
    dueUnit($this->project, $this->user, withQuestion: false);

    $session = $this->service->start($this->user->id, $this->project->id, 'due');

    expect($session->questions_total)->toBe(2)
        ->and(SessionQuestion::where('session_id', $session->id)->count())->toBe(2);
});

it('throws and rolls back when no due unit has a question', function () {
    dueUnit($this->project, $this->user, withQuestion: false);
    dueUnit($this->project, $this->user, withQuestion: false);

    expect(fn () => $this->service->start($this->user->id, $this->project->id, 'due'))
        ->toThrow(NoCardsAvailableException::class);

    expect(SessionLog::count())->toBe(0);
});

it('includes cards from every project in a global session', function () {
    $other = Project::factory()->for($this->user)->create();
    dueUnit($this->project, $this->user);
    dueUnit($other, $this->user);

    $session = $this->service->start($this->user->id, null, 'due');

    expect($session->project_id)->toBeNull()
        ->and($session->questions_total)->toBe(2);
});

it('selects not-yet-due cards for a voluntary session', function () {
    dueUnit($this->project, $this->user, dueAt: '+5 days'); // not due

    $session = $this->service->start($this->user->id, $this->project->id, 'voluntary');

    expect($session->session_type)->toBe('voluntary')
        ->and($session->questions_total)->toBe(1);
});

it('does not select another user\'s cards', function () {
    dueUnit($this->project, $this->user);
    $stranger = User::factory()->create();

    expect(fn () => $this->service->start($stranger->id, null, 'due'))
        ->toThrow(NoCardsAvailableException::class);
});
