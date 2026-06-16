<?php

use App\Jobs\ExtractKnowledgeJob;
use App\Jobs\PromoteUploadStagingJob;
use App\Models\Document;
use App\Models\Project;
use App\Models\UploadStaging;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Fake only the leaf job so the promotion job under test runs for real.
    Queue::fake([ExtractKnowledgeJob::class]);
    $this->user = User::factory()->create();
});

it('promotes a staging into a document and dispatches extraction', function () {
    $project = Project::factory()->for($this->user)->create();
    $staging = UploadStaging::factory()->for($this->user)->create([
        'assigned_project_id' => $project->id,
        'assigned_project_name' => $project->name,
        'classification_status' => 'awaiting_confirmation',
    ]);

    PromoteUploadStagingJob::dispatchSync($staging->id);

    expect(UploadStaging::find($staging->id))->toBeNull()
        ->and(Document::where('project_id', $project->id)->count())->toBe(1);

    Queue::assertPushed(ExtractKnowledgeJob::class);
});

it('does nothing when the staging no longer exists', function () {
    PromoteUploadStagingJob::dispatchSync(999);

    expect(Document::count())->toBe(0);
    Queue::assertNotPushed(ExtractKnowledgeJob::class);
});
