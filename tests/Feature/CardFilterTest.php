<?php

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

it('shows approved cards but not drafts', function () {
    KnowledgeUnit::factory()->for($this->project)->for($this->user)->approved()->create(['title' => 'BestaetigteKarte']);
    KnowledgeUnit::factory()->for($this->project)->for($this->user)->create(['unit_status' => 'draft', 'title' => 'EntwurfKarte']);

    Livewire::actingAs($this->user)
        ->test('cards.card-list', ['project' => $this->project])
        ->assertSee('BestaetigteKarte')
        ->assertDontSee('EntwurfKarte');
});

it('filters by type', function () {
    KnowledgeUnit::factory()->for($this->project)->for($this->user)->approved()->create(['type' => 'fact', 'title' => 'FaktKarte']);
    KnowledgeUnit::factory()->for($this->project)->for($this->user)->approved()->create(['type' => 'vocab', 'title' => 'VokabelKarte']);

    Livewire::actingAs($this->user)
        ->test('cards.card-list', ['project' => $this->project])
        ->set('filterType', 'fact')
        ->assertSee('FaktKarte')
        ->assertDontSee('VokabelKarte');
});

it('filters by topic', function () {
    KnowledgeUnit::factory()->for($this->project)->for($this->user)->approved()->create(['topic_tag' => 'Bilanzen', 'title' => 'BilanzKarte']);
    KnowledgeUnit::factory()->for($this->project)->for($this->user)->approved()->create(['topic_tag' => 'Steuern', 'title' => 'SteuerKarte']);

    Livewire::actingAs($this->user)
        ->test('cards.card-list', ['project' => $this->project])
        ->set('filterTopic', 'Bilanzen')
        ->assertSee('BilanzKarte')
        ->assertDontSee('SteuerKarte');
});

it('filters by learn status', function () {
    $fresh = KnowledgeUnit::factory()->for($this->project)->for($this->user)->approved()->create(['title' => 'NeuKarte']);
    $due = KnowledgeUnit::factory()->for($this->project)->for($this->user)->approved()->create(['title' => 'FaelligKarte']);

    ReviewState::where('knowledge_unit_id', $due->id)->update([
        'attempt_count' => 1,
        'due_at' => now()->subDay(),
    ]);

    Livewire::actingAs($this->user)
        ->test('cards.card-list', ['project' => $this->project])
        ->set('filterLearnStatus', 'faellig')
        ->assertSee('FaelligKarte')
        ->assertDontSee('NeuKarte')
        ->set('filterLearnStatus', 'neu')
        ->assertSee('NeuKarte')
        ->assertDontSee('FaelligKarte');
});

it('marks a card manually_edited when saved', function () {
    $card = KnowledgeUnit::factory()->for($this->project)->for($this->user)->approved()->create(['manually_edited' => false]);

    Livewire::actingAs($this->user)
        ->test('cards.card-list', ['project' => $this->project])
        ->call('startEdit', $card->id)
        ->set('editContent', 'Neuer Inhalt.')
        ->call('saveUnit')
        ->assertHasNoErrors();

    expect($card->fresh()->manually_edited)->toBeTrue()
        ->and($card->fresh()->content)->toBe('Neuer Inhalt.');
});

it('soft-deletes a card into the trash', function () {
    $card = KnowledgeUnit::factory()->for($this->project)->for($this->user)->approved()->create();

    Livewire::actingAs($this->user)
        ->test('cards.card-list', ['project' => $this->project])
        ->call('confirmDelete', $card->id)
        ->call('deleteCard');

    expect(KnowledgeUnit::find($card->id))->toBeNull()
        ->and(KnowledgeUnit::withTrashed()->find($card->id))->not->toBeNull();
});

it('paginates at 50 cards per page', function () {
    KnowledgeUnit::factory(51)->for($this->project)->for($this->user)->approved()->create();

    $paginator = Livewire::actingAs($this->user)
        ->test('cards.card-list', ['project' => $this->project])
        ->instance()->cards();

    expect($paginator->total())->toBe(51)
        ->and($paginator->perPage())->toBe(50)
        ->and($paginator->count())->toBe(50);
});

it('cannot edit a card from another project', function () {
    $foreign = KnowledgeUnit::factory()->for($this->user)->approved()->create();

    Livewire::actingAs($this->user)
        ->test('cards.card-list', ['project' => $this->project])
        ->call('startEdit', $foreign->id)
        ->assertStatus(404);
});

it('enforces ownership on the cards route', function () {
    $this->actingAs($this->user)->get(route('cards.index', $this->project))->assertOk();

    $foreignProject = Project::factory()->create();
    $this->actingAs($this->user)->get(route('cards.index', $foreignProject))->assertStatus(404);
});
