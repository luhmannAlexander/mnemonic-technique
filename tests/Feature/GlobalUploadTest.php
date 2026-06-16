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
        ->call('upload')
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
