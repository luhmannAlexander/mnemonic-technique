<?php

use App\Jobs\GenerateQuestionsJob;
use App\Models\KnowledgeUnit;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Approve a unit without firing the observer, so the job can be tested alone. */
function approvedUnit(array $attributes = []): KnowledgeUnit
{
    $unit = KnowledgeUnit::factory()->create(['unit_status' => 'draft']);
    $unit->updateQuietly(array_merge(['unit_status' => 'approved'], $attributes));

    return $unit;
}

it('generates one MC and one free-text question', function () {
    $unit = approvedUnit();

    GenerateQuestionsJob::dispatchSync($unit->id);

    expect(Question::where('knowledge_unit_id', $unit->id)->count())->toBe(2);

    $mc = Question::where('knowledge_unit_id', $unit->id)->where('kind', 'mc')->first();
    $free = Question::where('knowledge_unit_id', $unit->id)->where('kind', 'free')->first();

    expect($mc->options_json)->toHaveCount(4)
        ->and($mc->correct_answer)->toBe('Richtig')
        ->and($free->options_json)->toBeNull();
});

it('overwrites existing questions on regeneration via the unique key', function () {
    $unit = approvedUnit();

    GenerateQuestionsJob::dispatchSync($unit->id);
    GenerateQuestionsJob::dispatchSync($unit->id);

    expect(Question::where('knowledge_unit_id', $unit->id)->count())->toBe(2);
});

it('skips generation for manually edited units', function () {
    $unit = approvedUnit(['manually_edited' => true]);

    GenerateQuestionsJob::dispatchSync($unit->id);

    expect(Question::where('knowledge_unit_id', $unit->id)->count())->toBe(0);
});

it('does nothing for a draft unit', function () {
    $unit = KnowledgeUnit::factory()->create(['unit_status' => 'draft']);

    GenerateQuestionsJob::dispatchSync($unit->id);

    expect(Question::where('knowledge_unit_id', $unit->id)->count())->toBe(0);
});
