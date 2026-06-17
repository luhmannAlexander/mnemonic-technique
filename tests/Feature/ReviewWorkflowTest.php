<?php

use App\Jobs\GenerateQuestionsJob;
use App\Models\KnowledgeUnit;
use App\Models\Project;
use App\Models\ReviewState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();
});

it('lists only this project\'s draft units', function () {
    $draft = KnowledgeUnit::factory()->for($this->project)->for($this->user)->create([
        'unit_status' => 'draft', 'title' => 'Entwurf-Titel',
    ]);
    $approved = KnowledgeUnit::factory()->for($this->project)->for($this->user)->approved()->create([
        'title' => 'Bestaetigt-Titel',
    ]);
    $otherProject = KnowledgeUnit::factory()->for($this->user)->create([
        'unit_status' => 'draft', 'title' => 'Fremd-Titel',
    ]);

    Livewire::actingAs($this->user)
        ->test('review.review-list', ['project' => $this->project])
        ->assertSee('Entwurf-Titel')
        ->assertDontSee('Bestaetigt-Titel')
        ->assertDontSee('Fremd-Titel');
});

it('approving a unit dispatches GenerateQuestionsJob and seeds a review state', function () {
    $unit = KnowledgeUnit::factory()->for($this->project)->for($this->user)->create(['unit_status' => 'draft']);

    Livewire::actingAs($this->user)
        ->test('review.review-list', ['project' => $this->project])
        ->call('approve', $unit->id);

    expect($unit->fresh()->unit_status)->toBe('approved')
        ->and(ReviewState::where('knowledge_unit_id', $unit->id)->exists())->toBeTrue();

    Queue::assertPushed(GenerateQuestionsJob::class);
});

it('approveAll approves every draft and creates a review state for each', function () {
    KnowledgeUnit::factory(5)->for($this->project)->for($this->user)->create(['unit_status' => 'draft']);

    Livewire::actingAs($this->user)
        ->test('review.review-list', ['project' => $this->project])
        ->call('approveAll');

    expect(KnowledgeUnit::where('project_id', $this->project->id)->where('unit_status', 'draft')->count())->toBe(0)
        ->and(ReviewState::where('user_id', $this->user->id)->count())->toBe(5);

    Queue::assertPushed(GenerateQuestionsJob::class, 5);
});

it('discards a draft by hard-deleting it (no trash)', function () {
    $unit = KnowledgeUnit::factory()->for($this->project)->for($this->user)->create(['unit_status' => 'draft']);

    Livewire::actingAs($this->user)
        ->test('review.review-list', ['project' => $this->project])
        ->call('confirmDiscard', $unit->id)
        ->call('discard');

    expect(KnowledgeUnit::withTrashed()->find($unit->id))->toBeNull();
});

it('creates a manually-added unit as approved with no document', function () {
    Livewire::actingAs($this->user)
        ->test('review.review-list', ['project' => $this->project])
        ->call('startCreate')
        ->set('editType', 'concept')
        ->set('editTitle', 'Handgemacht')
        ->set('editContent', 'Selbst geschrieben.')
        ->set('editTechnique', 'loci')
        ->call('saveUnit')
        ->assertHasNoErrors();

    $unit = KnowledgeUnit::where('title', 'Handgemacht')->first();

    expect($unit)->not->toBeNull()
        ->and($unit->unit_status)->toBe('approved')
        ->and($unit->document_id)->toBeNull()
        ->and($unit->project_id)->toBe($this->project->id);

    Queue::assertPushed(GenerateQuestionsJob::class);
});

it('flags a draft as manually_edited when its content changes', function () {
    $unit = KnowledgeUnit::factory()->for($this->project)->for($this->user)->create([
        'unit_status' => 'draft', 'content' => 'Original.', 'manually_edited' => false,
    ]);

    Livewire::actingAs($this->user)
        ->test('review.review-list', ['project' => $this->project])
        ->call('startEdit', $unit->id)
        ->set('editContent', 'Komplett anders.')
        ->call('saveUnit')
        ->assertHasNoErrors();

    $unit->refresh();

    expect($unit->manually_edited)->toBeTrue()
        ->and($unit->content)->toBe('Komplett anders.')
        ->and($unit->unit_status)->toBe('draft');
});

it('does not flag manually_edited when only the title changes', function () {
    $unit = KnowledgeUnit::factory()->for($this->project)->for($this->user)->create([
        'unit_status' => 'draft', 'manually_edited' => false,
    ]);

    Livewire::actingAs($this->user)
        ->test('review.review-list', ['project' => $this->project])
        ->call('startEdit', $unit->id)
        ->set('editTitle', 'Nur neuer Titel')
        ->call('saveUnit');

    expect($unit->fresh()->manually_edited)->toBeFalse();
});

it('validates required fields when saving', function () {
    Livewire::actingAs($this->user)
        ->test('review.review-list', ['project' => $this->project])
        ->call('startCreate')
        ->set('editTitle', '')
        ->set('editContent', '')
        ->call('saveUnit')
        ->assertHasErrors(['editTitle', 'editContent']);
});

it('cannot approve a draft from another user', function () {
    $foreign = KnowledgeUnit::factory()->create(['unit_status' => 'draft']);

    Livewire::actingAs($this->user)
        ->test('review.review-list', ['project' => $this->project])
        ->call('approve', $foreign->id)
        ->assertStatus(404);
});

it('cannot approve an already-approved unit through review', function () {
    $approved = KnowledgeUnit::factory()->for($this->project)->for($this->user)->approved()->create();

    Livewire::actingAs($this->user)
        ->test('review.review-list', ['project' => $this->project])
        ->call('approve', $approved->id)
        ->assertStatus(404);
});

it('enforces ownership on the review route', function () {
    $this->actingAs($this->user)->get(route('review.index', $this->project))->assertOk();

    $foreignProject = Project::factory()->create();
    $this->actingAs($this->user)->get(route('review.index', $foreignProject))->assertStatus(404);
});
