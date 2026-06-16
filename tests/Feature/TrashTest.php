<?php

use App\Models\Document;
use App\Models\KnowledgeUnit;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('lists trashed documents for the owner', function () {
    $project = Project::factory()->for($this->user)->create(['name' => 'Altes Projekt']);
    $document = Document::factory()->for($project)->create(['filename' => 'alt.md']);
    $document->delete();

    Livewire::actingAs($this->user)
        ->test('trash.trash-list')
        ->assertSee('alt.md')
        ->assertSee('Altes Projekt');

    expect(Document::onlyTrashed()->count())->toBe(1);
});

it('restores a project and its cascaded documents', function () {
    $project = Project::factory()->for($this->user)->create();
    $document = Document::factory()->for($project)->create();
    $project->delete();

    expect($document->fresh()->trashed())->toBeTrue();

    Livewire::actingAs($this->user)
        ->test('trash.trash-list')
        ->call('restore', 'project', $project->id);

    expect($project->fresh()->trashed())->toBeFalse()
        ->and($document->fresh()->trashed())->toBeFalse();
});

it('restores a deleted document together with its trashed parent project', function () {
    $project = Project::factory()->for($this->user)->create();
    $document = Document::factory()->for($project)->create();
    $project->delete(); // cascades to document

    Livewire::actingAs($this->user)
        ->test('trash.trash-list')
        ->call('restore', 'document', $document->id);

    expect($document->fresh()->trashed())->toBeFalse()
        ->and($project->fresh()->trashed())->toBeFalse();
});

it('permanently deletes a trashed project and its children', function () {
    $project = Project::factory()->for($this->user)->create();
    $document = Document::factory()->for($project)->create();
    $unit = KnowledgeUnit::factory()->for($document)->create();
    $project->delete();

    Livewire::actingAs($this->user)
        ->test('trash.trash-list')
        ->call('confirmForceDelete', 'project', $project->id)
        ->call('forceDelete');

    expect(Project::withTrashed()->find($project->id))->toBeNull()
        ->and(Document::withTrashed()->find($document->id))->toBeNull()
        ->and(KnowledgeUnit::withTrashed()->find($unit->id))->toBeNull();
});

it('cannot restore another user\'s trashed project', function () {
    $project = Project::factory()->for(User::factory())->create();
    $project->delete();

    Livewire::actingAs($this->user)
        ->test('trash.trash-list')
        ->call('restore', 'project', $project->id)
        ->assertStatus(404);

    expect($project->fresh()->trashed())->toBeTrue();
});
