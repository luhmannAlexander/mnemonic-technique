<?php

use App\Jobs\ExtractKnowledgeJob;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();
});

it('renders the raw markdown as html', function () {
    $document = Document::factory()->for($this->project)->create([
        'raw_markdown' => "# Kapitel Eins\n\nEin **wichtiger** Absatz.",
    ]);

    actingAs($this->user)
        ->get(route('documents.show', [$this->project, $document]))
        ->assertOk()
        ->assertSee('Kapitel Eins')
        ->assertSee('<strong>wichtiger</strong>', false);
});

it('strips embedded raw html from the markdown (xss safety)', function () {
    $document = Document::factory()->for($this->project)->create([
        'raw_markdown' => "# Titel\n\n<script>alert('x')</script>",
    ]);

    actingAs($this->user)
        ->get(route('documents.show', [$this->project, $document]))
        ->assertOk()
        ->assertDontSee('<script>alert', false);
});

it('rejects a document that belongs to a different project (IDOR)', function () {
    // Two projects owned by the same user; document belongs to the other one.
    $otherProject = Project::factory()->for($this->user)->create();
    $foreignDocument = Document::factory()->for($otherProject)->create();

    Livewire::actingAs($this->user)
        ->test('documents.document-detail', ['project' => $this->project, 'document' => $foreignDocument])
        ->assertStatus(404);
});

it('retries an errored document from the detail page', function () {
    Queue::fake();
    $document = Document::factory()->for($this->project)->error()->create();

    Livewire::actingAs($this->user)
        ->test('documents.document-detail', ['project' => $this->project, 'document' => $document])
        ->call('retry');

    expect($document->fresh()->status)->toBe('pending')
        ->and($document->fresh()->error_detail)->toBeNull();

    Queue::assertPushed(ExtractKnowledgeJob::class);
});
