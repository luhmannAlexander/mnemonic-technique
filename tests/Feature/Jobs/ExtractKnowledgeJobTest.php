<?php

use App\Contracts\LLMServiceInterface;
use App\Exceptions\LLMException;
use App\Jobs\ExtractKnowledgeJob;
use App\Models\Document;
use App\Models\KnowledgeUnit;
use App\Services\FakeLLMService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates knowledge units and marks the document done', function () {
    $document = Document::factory()->create(['status' => 'pending']);

    ExtractKnowledgeJob::dispatchSync($document->id);

    $document->refresh();

    expect($document->status)->toBe('done')
        ->and($document->extraction_attempts)->toBe(1)
        ->and($document->extracted_unit_count)->toBe(1)
        ->and(KnowledgeUnit::where('document_id', $document->id)->count())->toBe(1);
});

it('persists units as draft with the owning project and user', function () {
    $document = Document::factory()->create(['status' => 'pending']);

    ExtractKnowledgeJob::dispatchSync($document->id);

    $unit = KnowledgeUnit::where('document_id', $document->id)->first();

    expect($unit->unit_status)->toBe('draft')
        ->and($unit->project_id)->toBe($document->project_id)
        ->and($unit->user_id)->toBe($document->user_id);
});

it('marks the document as error when the LLM fails', function () {
    $document = Document::factory()->create(['status' => 'pending']);

    app()->bind(LLMServiceInterface::class, fn () => new class extends FakeLLMService
    {
        public function extract(string $markdown): array
        {
            throw new LLMException('Timeout nach 120 s.');
        }
    });

    expect(fn () => ExtractKnowledgeJob::dispatchSync($document->id))->toThrow(LLMException::class);

    $document->refresh();

    expect($document->status)->toBe('error')
        ->and($document->error_detail)->toBe('Timeout nach 120 s.')
        ->and(KnowledgeUnit::where('document_id', $document->id)->count())->toBe(0);
});

it('stops after two attempts without calling the LLM', function () {
    $document = Document::factory()->create(['status' => 'processing', 'extraction_attempts' => 2]);

    ExtractKnowledgeJob::dispatchSync($document->id);

    $document->refresh();

    expect($document->status)->toBe('error')
        ->and($document->error_detail)->toBe('Maximale Anzahl Versuche erreicht.')
        ->and(KnowledgeUnit::where('document_id', $document->id)->count())->toBe(0);
});

it('does nothing when the document no longer exists', function () {
    ExtractKnowledgeJob::dispatchSync(999);

    expect(KnowledgeUnit::count())->toBe(0);
});
