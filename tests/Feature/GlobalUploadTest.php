<?php

use App\Jobs\ClassifyUploadJob;
use App\Jobs\ExtractKnowledgeJob;
use App\Models\Document;
use App\Models\Project;
use App\Models\UploadStaging;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Fake the leaf jobs only, so PromoteUploadStagingJob::dispatchSync still runs.
    Queue::fake([ClassifyUploadJob::class, ExtractKnowledgeJob::class]);
    $this->user = User::factory()->create();
});

it('stages an uploaded file and dispatches classification', function () {
    Livewire::actingAs($this->user)
        ->test('upload.global-upload')
        ->set('files', [UploadedFile::fake()->createWithContent('notizen.md', '# Inhalt')])
        ->call('save')
        ->assertHasNoErrors();

    $staging = UploadStaging::first();

    expect($staging)->not->toBeNull()
        ->and($staging->user_id)->toBe($this->user->id)
        ->and($staging->classification_status)->toBe('pending')
        ->and($staging->expires_at)->not->toBeNull();

    Queue::assertPushed(ClassifyUploadJob::class);
});

it('manually assigns a staging to an existing project and promotes it on confirm', function () {
    $project = Project::factory()->for($this->user)->create();
    $staging = UploadStaging::factory()->for($this->user)->create();

    Livewire::actingAs($this->user)
        ->test('upload.global-upload')
        ->set("assign.{$staging->id}", (string) $project->id)
        ->call('assignStaging', $staging->id)
        ->call('confirmAssignment', $staging->id);

    expect(UploadStaging::find($staging->id))->toBeNull()
        ->and(Document::where('project_id', $project->id)->count())->toBe(1)
        ->and(Document::first()->filename)->toBe($staging->filename);

    Queue::assertPushed(ExtractKnowledgeJob::class);
});

it('creates a new project from a staging assignment on confirm', function () {
    $staging = UploadStaging::factory()->for($this->user)->create();

    Livewire::actingAs($this->user)
        ->test('upload.global-upload')
        ->set("assign.{$staging->id}", '__new__')
        ->set("newProjectName.{$staging->id}", 'Frisches Projekt')
        ->call('assignStaging', $staging->id)
        ->call('confirmAssignment', $staging->id);

    $project = Project::where('user_id', $this->user->id)->where('name', 'Frisches Projekt')->first();

    expect($project)->not->toBeNull()
        ->and(Document::where('project_id', $project->id)->count())->toBe(1)
        ->and(UploadStaging::find($staging->id))->toBeNull();
});

it('requires a name when assigning to a new project', function () {
    $staging = UploadStaging::factory()->for($this->user)->create();

    Livewire::actingAs($this->user)
        ->test('upload.global-upload')
        ->set("assign.{$staging->id}", '__new__')
        ->set("newProjectName.{$staging->id}", '')
        ->call('assignStaging', $staging->id)
        ->assertHasErrors("newProjectName.{$staging->id}");

    expect($staging->fresh()->classification_status)->toBe('pending');
});

it('discards a staging', function () {
    $staging = UploadStaging::factory()->for($this->user)->create();

    Livewire::actingAs($this->user)
        ->test('upload.global-upload')
        ->call('discard', $staging->id);

    expect(UploadStaging::find($staging->id))->toBeNull();
});

it('cannot touch another user\'s staging', function () {
    $staging = UploadStaging::factory()->for(User::factory())->create();

    Livewire::actingAs($this->user)
        ->test('upload.global-upload')
        ->call('discard', $staging->id)
        ->assertStatus(404);

    expect(UploadStaging::find($staging->id))->not->toBeNull();
});

it('shows the AI project suggestions once classification succeeds', function () {
    UploadStaging::factory()->for($this->user)->create([
        'classification_status' => 'awaiting_confirmation',
        'ai_suggestion_payload' => ['suggestions' => [
            ['type' => 'new', 'project_id' => null, 'name' => 'Linux Pakete', 'reason' => 'Passt zum Inhalt.'],
        ]],
    ]);

    Livewire::actingAs($this->user)
        ->test('upload.global-upload')
        ->assertSee('Linux Pakete')
        ->assertSee('Passt zum Inhalt.')
        ->assertDontSee('Automatische Zuordnung gerade nicht verfügbar');
});

it('accepts a new-project AI suggestion and stages it for confirmation', function () {
    $staging = UploadStaging::factory()->for($this->user)->create([
        'classification_status' => 'awaiting_confirmation',
        'ai_suggestion_payload' => ['suggestions' => [
            ['type' => 'new', 'project_id' => null, 'name' => 'Linux Pakete', 'reason' => 'x'],
        ]],
    ]);

    Livewire::actingAs($this->user)
        ->test('upload.global-upload')
        ->call('acceptSuggestion', $staging->id, 0);

    $fresh = $staging->fresh();
    expect($fresh->assigned_project_name)->toBe('Linux Pakete')
        ->and($fresh->assigned_project_id)->toBeNull()
        ->and($fresh->classification_status)->toBe('awaiting_confirmation');
});

it('accepts an existing-project AI suggestion', function () {
    $project = Project::factory()->for($this->user)->create(['name' => 'Linux']);
    $staging = UploadStaging::factory()->for($this->user)->create([
        'classification_status' => 'awaiting_confirmation',
        'ai_suggestion_payload' => ['suggestions' => [
            ['type' => 'existing', 'project_id' => $project->id, 'name' => null, 'reason' => 'x'],
        ]],
    ]);

    Livewire::actingAs($this->user)
        ->test('upload.global-upload')
        ->call('acceptSuggestion', $staging->id, 0);

    $fresh = $staging->fresh();
    expect($fresh->assigned_project_id)->toBe($project->id)
        ->and($fresh->assigned_project_name)->toBe('Linux');
});

it('rejects an AI suggestion pointing at a project the user does not own', function () {
    $stranger = Project::factory()->for(User::factory())->create();
    $staging = UploadStaging::factory()->for($this->user)->create([
        'classification_status' => 'awaiting_confirmation',
        'ai_suggestion_payload' => ['suggestions' => [
            ['type' => 'existing', 'project_id' => $stranger->id, 'name' => null, 'reason' => 'x'],
        ]],
    ]);

    Livewire::actingAs($this->user)
        ->test('upload.global-upload')
        ->call('acceptSuggestion', $staging->id, 0)
        ->assertStatus(404);

    expect($staging->fresh()->assigned_project_id)->toBeNull();
});

it('polls and shows a classifying state while the AI is still working', function () {
    UploadStaging::factory()->for($this->user)->create([
        'classification_status' => 'classifying',
    ]);

    Livewire::actingAs($this->user)
        ->test('upload.global-upload')
        ->assertSee('Wird automatisch zugeordnet')
        ->assertSeeHtml('wire:poll')
        ->assertDontSee('Automatische Zuordnung gerade nicht verfügbar');
});

it('falls back to manual assignment when classification failed', function () {
    UploadStaging::factory()->for($this->user)->create([
        'classification_status' => 'failed',
        'classification_error' => 'LLM down',
    ]);

    Livewire::actingAs($this->user)
        ->test('upload.global-upload')
        ->assertSee('Automatische Zuordnung gerade nicht verfügbar');
});
