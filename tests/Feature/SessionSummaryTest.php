<?php

use App\Models\Attempt;
use App\Models\SessionLog;
use App\Models\SessionQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake(); // the factory chain creates units; keep the observer's job out of the way
    $this->user = User::factory()->create();
});

it('shows totals and a per-question result list', function () {
    $session = SessionLog::factory()->for($this->user)->create([
        'status' => 'finished', 'questions_total' => 2,
        'questions_correct' => 1, 'questions_wrong' => 1, 'finished_at' => now(),
    ]);
    $sq1 = SessionQuestion::factory()->create(['session_id' => $session->id, 'position' => 1]);
    Attempt::factory()->create(['session_question_id' => $sq1->id, 'result' => 'correct']);
    $sq2 = SessionQuestion::factory()->create(['session_id' => $session->id, 'position' => 2]);
    Attempt::factory()->create(['session_question_id' => $sq2->id, 'result' => 'wrong']);

    Livewire::actingAs($this->user)
        ->test('practice.session-summary', ['session' => $session])
        ->assertSee('1 von 2 richtig')
        ->assertSee('1 falsch');
});

it('marks a pending free-text grade as "wird bewertet" and polls', function () {
    $session = SessionLog::factory()->for($this->user)->create([
        'status' => 'finished', 'questions_total' => 1, 'questions_pending' => 1, 'finished_at' => now(),
    ]);
    $sq = SessionQuestion::factory()->create(['session_id' => $session->id, 'position' => 1]);
    Attempt::factory()->create(['session_question_id' => $sq->id, 'kind' => 'free', 'result' => 'pending']);

    Livewire::actingAs($this->user)
        ->test('practice.session-summary', ['session' => $session])
        ->assertSee('wird bewertet')
        ->assertSee('wire:poll.3s', escape: false);
});

it('stops polling once every grade is in', function () {
    $session = SessionLog::factory()->for($this->user)->create([
        'status' => 'finished', 'questions_total' => 1, 'questions_correct' => 1,
        'questions_pending' => 0, 'finished_at' => now(),
    ]);
    $sq = SessionQuestion::factory()->create(['session_id' => $session->id, 'position' => 1]);
    Attempt::factory()->create(['session_question_id' => $sq->id, 'result' => 'correct']);

    Livewire::actingAs($this->user)
        ->test('practice.session-summary', ['session' => $session])
        ->assertDontSee('wire:poll', escape: false);
});

it('renders for the owner over HTTP', function () {
    $session = SessionLog::factory()->for($this->user)->create(['status' => 'finished']);

    $this->actingAs($this->user)
        ->get(route('practice.summary', $session))
        ->assertOk()
        ->assertSee('Zum Dashboard');
});

it('returns 404 for another user\'s summary', function () {
    $session = SessionLog::factory()->for($this->user)->create(['status' => 'finished']);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('practice.summary', $session))
        ->assertNotFound();
});
