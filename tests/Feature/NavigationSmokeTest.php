<?php

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('renders the top-level authenticated pages for an owner', function (string $route) {
    $user = User::factory()->create();

    actingAs($user)->get(route($route))->assertOk();
})->with([
    'dashboard',
    'projects.index',
    'upload.index',
    'trash.index',
]);

it('renders project-scoped pages for the owner', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $document = Document::factory()->for($project)->create();

    actingAs($user)->get(route('documents.index', $project))->assertOk();
    actingAs($user)->get(route('documents.show', [$project, $document]))->assertOk();
});

it('returns 404 for a document under a project the user does not own', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($other)->create();
    $document = Document::factory()->for($project)->create();

    actingAs($user)->get(route('documents.index', $project))->assertNotFound();
    actingAs($user)->get(route('documents.show', [$project, $document]))->assertNotFound();
});
