<?php

use App\Models\Document;
use App\Models\Project;
use App\Models\UploadStaging;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('permanently deletes items trashed more than 30 days ago and keeps recent ones', function () {
    $old = Project::factory()->for($this->user)->create();
    $old->delete();
    $old->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();

    $recent = Project::factory()->for($this->user)->create();
    $recent->delete();

    artisan('trash:purge')->assertSuccessful();

    expect(Project::withTrashed()->find($old->id))->toBeNull()
        ->and(Project::withTrashed()->find($recent->id))->not->toBeNull();
});

it('cascades permanent deletion to trashed children', function () {
    $project = Project::factory()->for($this->user)->create();
    $document = Document::factory()->for($project)->create();
    $project->delete();
    $project->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();

    artisan('trash:purge')->assertSuccessful();

    expect(Document::withTrashed()->find($document->id))->toBeNull();
});

it('purges expired unconfirmed upload stagings', function () {
    $expired = UploadStaging::factory()->for($this->user)->create([
        'expires_at' => now()->subDay(),
        'confirmed_at' => null,
    ]);
    $fresh = UploadStaging::factory()->for($this->user)->create([
        'expires_at' => now()->addDays(7),
    ]);

    artisan('trash:purge')->assertSuccessful();

    expect(UploadStaging::find($expired->id))->toBeNull()
        ->and(UploadStaging::find($fresh->id))->not->toBeNull();
});
