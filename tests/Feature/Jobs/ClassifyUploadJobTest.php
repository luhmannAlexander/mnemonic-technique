<?php

use App\Contracts\LLMServiceInterface;
use App\Exceptions\LLMException;
use App\Jobs\ClassifyUploadJob;
use App\Models\Project;
use App\Models\UploadStaging;
use App\Models\User;
use App\Services\FakeLLMService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('stores the suggestion and awaits confirmation on success', function () {
    $project = Project::factory()->for($this->user)->create(['name' => 'Spanisch']);
    $staging = UploadStaging::factory()->for($this->user)->create(['classification_status' => 'pending']);

    ClassifyUploadJob::dispatchSync($staging->id);

    $staging->refresh();

    expect($staging->classification_status)->toBe('awaiting_confirmation')
        ->and($staging->ai_suggestion_payload)->toHaveKey('suggestions')
        ->and($staging->ai_suggestion_payload['suggestions'][0]['project_id'])->toBe($project->id);
});

it('marks the staging failed when the LLM throws', function () {
    $staging = UploadStaging::factory()->for($this->user)->create(['classification_status' => 'pending']);

    app()->bind(LLMServiceInterface::class, fn () => new class extends FakeLLMService
    {
        public function classifyUpload(string $markdown, array $existingProjects): array
        {
            throw new LLMException('Modell nicht erreichbar.');
        }
    });

    expect(fn () => ClassifyUploadJob::dispatchSync($staging->id))->toThrow(LLMException::class);

    $staging->refresh();

    expect($staging->classification_status)->toBe('failed')
        ->and($staging->classification_error)->toBe('Modell nicht erreichbar.');
});

it('skips a staging that was already confirmed', function () {
    $staging = UploadStaging::factory()->for($this->user)->create([
        'classification_status' => 'awaiting_confirmation',
        'confirmed_at' => now(),
    ]);

    ClassifyUploadJob::dispatchSync($staging->id);

    expect($staging->fresh()->classification_status)->toBe('awaiting_confirmation');
});

it('does nothing when the staging no longer exists', function () {
    ClassifyUploadJob::dispatchSync(999);
})->throwsNoExceptions();
