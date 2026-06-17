<?php

use App\Jobs\GenerateQuestionsJob;
use App\Models\KnowledgeUnit;
use App\Models\Project;
use App\Models\ReviewState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

it('seeds a review state and queues questions when a draft is approved', function () {
    $unit = KnowledgeUnit::factory()->create(['unit_status' => 'draft']);

    $unit->update(['unit_status' => 'approved']);

    expect(ReviewState::where('knowledge_unit_id', $unit->id)->where('user_id', $unit->user_id)->exists())->toBeTrue();
    Queue::assertPushed(GenerateQuestionsJob::class);
});

it('fires the approve side effects for a unit created already approved', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $unit = KnowledgeUnit::factory()->for($project)->for($user)->create([
        'document_id' => null,
        'unit_status' => 'approved',
    ]);

    expect(ReviewState::where('knowledge_unit_id', $unit->id)->exists())->toBeTrue();
    Queue::assertPushed(GenerateQuestionsJob::class);
});

it('does not react to edits that leave the unit a draft', function () {
    $unit = KnowledgeUnit::factory()->create(['unit_status' => 'draft']);

    $unit->update(['title' => 'Neuer Titel']);

    expect(ReviewState::where('knowledge_unit_id', $unit->id)->exists())->toBeFalse();
    Queue::assertNotPushed(GenerateQuestionsJob::class);
});

it('does not re-seed the review state when an approved unit is edited', function () {
    $unit = KnowledgeUnit::factory()->create(['unit_status' => 'draft']);
    $unit->update(['unit_status' => 'approved']);

    $state = ReviewState::where('knowledge_unit_id', $unit->id)->first();
    $state->update(['priority' => 99]);

    $unit->update(['title' => 'Bearbeitet']);

    expect(ReviewState::where('knowledge_unit_id', $unit->id)->first()->priority)->toBe(99);
});

it('keeps approval idempotent', function () {
    $unit = KnowledgeUnit::factory()->create(['unit_status' => 'draft']);

    $unit->update(['unit_status' => 'approved']);
    $unit->update(['unit_status' => 'approved']);

    expect(ReviewState::where('knowledge_unit_id', $unit->id)->count())->toBe(1);
});
