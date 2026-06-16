<?php

use App\Models\Document;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('soft-deletes child documents when the project is deleted', function () {
    $project = Project::factory()->create();
    $document = Document::factory()->for($project)->create();

    $project->delete();

    expect($document->fresh()->trashed())->toBeTrue();
});

it('restores child documents cascaded with the project', function () {
    $project = Project::factory()->create();
    $document = Document::factory()->for($project)->create();

    $project->delete();
    $project->restore();

    expect($document->fresh()->trashed())->toBeFalse();
});
