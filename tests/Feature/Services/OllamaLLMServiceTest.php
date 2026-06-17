<?php

use App\Exceptions\LLMException;
use App\Services\OllamaLLMService;
use Illuminate\Support\Facades\Http;

function ollamaService(): OllamaLLMService
{
    return new OllamaLLMService('http://ollama:11434', 'qwen3:14b', 120, 60);
}

function ollamaReplies(string $json): void
{
    Http::fake(['*/api/chat' => Http::response(['message' => ['content' => $json]])]);
}

it('parses a valid extraction response', function () {
    ollamaReplies('{"units":[{"type":"fact","title":"Test","content":"Inhalt","source_ref":"test.md","topic_tag":"Test","technique":"spaced","technique_material":null}]}');

    $result = ollamaService()->extract("# Test\nDas ist ein Test.");

    expect($result['units'])->toHaveCount(1)
        ->and($result['units'][0]['type'])->toBe('fact')
        ->and($result['units'][0]['title'])->toBe('Test');
});

it('sends format:json and disables thinking', function () {
    ollamaReplies('{"units":[]}');

    ollamaService()->extract('test');

    Http::assertSent(function ($request) {
        return $request['format'] === 'json'
            && $request['think'] === false
            && $request['stream'] === false
            && $request['model'] === 'qwen3:14b';
    });
});

it('throws LLMException on HTTP failure', function () {
    Http::fake(['*/api/chat' => Http::response([], 500)]);

    expect(fn () => ollamaService()->extract('test'))->toThrow(LLMException::class);
});

it('throws LLMException on non-JSON content', function () {
    ollamaReplies('das ist kein JSON');

    expect(fn () => ollamaService()->extract('test'))->toThrow(LLMException::class);
});

it('throws LLMException when a required key is missing', function () {
    ollamaReplies('{"something_else":[]}');

    expect(fn () => ollamaService()->extract('test'))->toThrow(LLMException::class, "Schlüssel 'units' fehlt");
});

it('strips markdown code fences before decoding', function () {
    ollamaReplies("```json\n{\"units\":[]}\n```");

    expect(ollamaService()->extract('test')['units'])->toBe([]);
});

it('parses classification suggestions', function () {
    ollamaReplies('{"suggestions":[{"type":"existing","project_id":7,"name":null,"reason":"Passt."}]}');

    $result = ollamaService()->classifyUpload('text', [['id' => 7, 'name' => 'Spanisch']]);

    expect($result['suggestions'][0]['project_id'])->toBe(7);
});

it('parses generated questions', function () {
    ollamaReplies('{"mc":{"prompt":"F?","options":[{"text":"A","correct":true}],"correct_answer":"A"},"free":{"prompt":"Erkläre.","correct_answer":"Antwort"}}');

    $result = ollamaService()->generateQuestions(['title' => 'T', 'content' => 'C']);

    expect($result['mc']['correct_answer'])->toBe('A')
        ->and($result['free']['prompt'])->toBe('Erkläre.');
});

it('unwraps the priorities array', function () {
    ollamaReplies('{"priorities":[{"knowledge_unit_id":3,"priority":80,"due_at":"2026-06-18T00:00:00+00:00"}]}');

    $result = ollamaService()->prioritiseReview([['id' => 3]]);

    expect($result)->toHaveCount(1)
        ->and($result[0]['knowledge_unit_id'])->toBe(3);
});
