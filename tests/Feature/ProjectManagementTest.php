<?php

use App\Models\KnowledgeUnit;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('links the "Jetzt üben" button to the project practice session when approved cards exist', function () {
    Queue::fake(); // capture the observer's GenerateQuestionsJob
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    KnowledgeUnit::factory()->approved()->create([
        'project_id' => $project->id, 'user_id' => $user->id, 'document_id' => null,
    ]);

    Livewire::actingAs($user)
        ->test('projects.project-overview', ['project' => $project])
        ->assertSee('Jetzt üben')
        ->assertSeeHtml(route('practice.project', $project));
});

it('creates a project and redirects to its overview', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('projects.project-list')
        ->set('name', 'IHK Prüfung')
        ->set('description', 'Vorbereitung')
        ->call('createProject')
        ->assertRedirect(route('projects.show', Project::first()));

    expect(Project::where('user_id', $user->id)->count())->toBe(1)
        ->and(Project::first()->name)->toBe('IHK Prüfung');
});

it('requires a project name', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('projects.project-list')
        ->set('name', '')
        ->call('createProject')
        ->assertHasErrors(['name' => 'required']);

    expect(Project::count())->toBe(0);
});

it('renames an owned project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'Alt']);

    Livewire::actingAs($user)
        ->test('projects.project-list')
        ->call('startRename', $project->id)
        ->set('renameName', 'Neu')
        ->call('renameProject')
        ->assertHasNoErrors();

    expect($project->fresh()->name)->toBe('Neu');
});

it('soft-deletes an owned project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('projects.project-list')
        ->call('confirmDelete', $project->id)
        ->call('deleteProject');

    expect($project->fresh()->trashed())->toBeTrue();
});

it('cannot delete another user\'s project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $intruder = User::factory()->create();

    Livewire::actingAs($intruder)
        ->test('projects.project-list')
        ->call('confirmDelete', $project->id)
        ->assertStatus(404);

    expect($project->fresh()->trashed())->toBeFalse();
});

it('returns 404 when viewing another user\'s project overview', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $intruder = User::factory()->create();

    actingAs($intruder)
        ->get(route('projects.show', $project))
        ->assertNotFound();
});

it('shows the owner their project overview', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee($project->name);
});
