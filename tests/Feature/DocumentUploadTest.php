<?php

use App\Jobs\ExtractKnowledgeJob;
use App\Models\Document;
use App\Models\KnowledgeUnit;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();
});

it('rejects non-markdown files', function () {
    Livewire::actingAs($this->user)
        ->test('documents.document-list', ['project' => $this->project])
        ->set('files', [UploadedFile::fake()->create('notes.pdf', 100)])
        ->call('save')
        ->assertHasErrors(['files.0']);

    expect(Document::count())->toBe(0);
});

it('rejects files over 2MB', function () {
    Livewire::actingAs($this->user)
        ->test('documents.document-list', ['project' => $this->project])
        ->set('files', [UploadedFile::fake()->create('gross.md', 2049)])
        ->call('save')
        ->assertHasErrors(['files.0']);

    expect(Document::count())->toBe(0);
});

it('stores an uploaded markdown document as pending and dispatches extraction', function () {
    Livewire::actingAs($this->user)
        ->test('documents.document-list', ['project' => $this->project])
        ->set('files', [UploadedFile::fake()->createWithContent('kapitel-1.md', "# Titel\nInhalt")])
        ->call('save')
        ->assertHasNoErrors();

    $document = Document::first();

    expect($document)->not->toBeNull()
        ->and($document->status)->toBe('pending')
        ->and($document->project_id)->toBe($this->project->id)
        ->and($document->user_id)->toBe($this->user->id)
        ->and($document->filename)->toBe('kapitel-1.md')
        ->and($document->raw_markdown)->toContain('# Titel');

    Queue::assertPushed(ExtractKnowledgeJob::class);
});

it('retries an errored document back to pending and re-dispatches', function () {
    $document = Document::factory()->for($this->project)->error()->create();

    Livewire::actingAs($this->user)
        ->test('documents.document-list', ['project' => $this->project])
        ->call('retry', $document->id);

    expect($document->fresh()->status)->toBe('pending')
        ->and($document->fresh()->extraction_attempts)->toBe(0)
        ->and($document->fresh()->error_detail)->toBeNull();

    Queue::assertPushed(ExtractKnowledgeJob::class);
});

it('soft-deletes a document and cascades to its knowledge units', function () {
    $document = Document::factory()->for($this->project)->create();
    $unit = KnowledgeUnit::factory()->for($document)->create();

    Livewire::actingAs($this->user)
        ->test('documents.document-list', ['project' => $this->project])
        ->call('confirmDelete', $document->id)
        ->call('deleteDocument');

    expect($document->fresh()->trashed())->toBeTrue()
        ->and($unit->fresh()->trashed())->toBeTrue();
});

it('cannot open the document list for another user\'s project', function () {
    $project = Project::factory()->for(User::factory())->create();

    Livewire::actingAs($this->user)
        ->test('documents.document-list', ['project' => $project])
        ->assertStatus(404);
});
