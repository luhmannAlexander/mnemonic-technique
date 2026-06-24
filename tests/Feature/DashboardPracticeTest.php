<?php

use App\Models\KnowledgeUnit;
use App\Models\Project;
use App\Models\ReviewState;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake(); // observer's GenerateQuestionsJob is captured, not run
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();
});

/** Approved unit; due in the past unless told otherwise. */
function dashboardUnit(Project $project, User $user, string $dueAt = '-1 day'): KnowledgeUnit
{
    $unit = KnowledgeUnit::factory()->approved()->create([
        'project_id' => $project->id, 'user_id' => $user->id, 'document_id' => null,
    ]);
    ReviewState::where('knowledge_unit_id', $unit->id)->update(['due_at' => now()->modify($dueAt)]);

    return $unit;
}

it('shows the due count and a "Jetzt üben" call to action', function () {
    dashboardUnit($this->project, $this->user);
    dashboardUnit($this->project, $this->user);

    Livewire::actingAs($this->user)
        ->test('dashboard')
        ->assertSet('dueCount', 2)
        ->assertSee('Heute fällig: 2 Karten')
        ->assertSee('Jetzt üben')
        ->assertSeeHtml(route('practice.today'));
});

it('offers "Trotzdem üben" when cards exist but none are due', function () {
    dashboardUnit($this->project, $this->user, dueAt: '+3 days');

    Livewire::actingAs($this->user)
        ->test('dashboard')
        ->assertSet('dueCount', 0)
        ->assertSee('alles erledigt')
        ->assertSee('Trotzdem üben')
        ->assertSeeHtml(route('practice.today', ['type' => 'voluntary']));
});

it('prompts to upload when no approved cards exist yet', function () {
    Livewire::actingAs($this->user)
        ->test('dashboard')
        ->assertSet('approvedCardCount', 0)
        ->assertSee('Noch keine Karten zum Üben');
});

it('shows a resume card for an interrupted session', function () {
    dashboardUnit($this->project, $this->user);
    $session = SessionLog::factory()->for($this->user)->create([
        'status' => 'interrupted', 'project_id' => $this->project->id,
        'questions_total' => 10, 'current_question_index' => 3,
    ]);

    Livewire::actingAs($this->user)
        ->test('dashboard')
        ->assertSee('Session fortsetzen')
        ->assertSee('Frage 4 von 10')
        ->assertSee('Neu starten')
        ->assertSeeHtml(route('practice.session', $session));
});

it('restart abandons the interrupted session and redirects to a fresh one', function () {
    dashboardUnit($this->project, $this->user);
    $session = SessionLog::factory()->for($this->user)->create([
        'status' => 'interrupted', 'project_id' => null, // global slot
        'questions_total' => 10, 'current_question_index' => 3,
    ]);

    Livewire::actingAs($this->user)
        ->test('dashboard')
        ->call('restart')
        ->assertRedirect(route('practice.today'));

    expect($session->fresh()->status)->toBe('abandoned');
});

it('does not surface another user\'s due cards', function () {
    $stranger = User::factory()->create();
    $strangerProject = Project::factory()->for($stranger)->create();
    dashboardUnit($strangerProject, $stranger);

    Livewire::actingAs($this->user)
        ->test('dashboard')
        ->assertSet('dueCount', 0);
});
