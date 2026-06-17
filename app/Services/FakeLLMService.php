<?php

namespace App\Services;

use App\Contracts\LLMServiceInterface;
use Illuminate\Support\Carbon;

/**
 * Deterministic stand-in for {@see OllamaLLMService}, bound in the test suite
 * (tests/Pest.php) so feature tests never touch the real model [PRD K-6].
 * Returns fixed, schema-valid payloads that mirror the real contract.
 */
class FakeLLMService implements LLMServiceInterface
{
    public function extract(string $markdown): array
    {
        return ['units' => [
            [
                'type' => 'fact',
                'title' => 'Testfakt',
                'content' => 'Testinhalt',
                'source_ref' => 'test.md',
                'topic_tag' => 'Testtag',
                'technique' => 'spaced',
                'technique_material' => 'Testtechnik',
            ],
        ]];
    }

    public function classifyUpload(string $markdown, array $existingProjects): array
    {
        if ($existingProjects !== []) {
            return ['suggestions' => [[
                'type' => 'existing',
                'project_id' => $existingProjects[0]['id'],
                'name' => null,
                'reason' => 'Testvorschlag (vorhandenes Projekt).',
            ]]];
        }

        return ['suggestions' => [[
            'type' => 'new',
            'project_id' => null,
            'name' => 'Testprojekt',
            'reason' => 'Testvorschlag (neues Projekt).',
        ]]];
    }

    public function generateQuestions(array $unit): array
    {
        return [
            'mc' => [
                'prompt' => 'Testfrage?',
                'options' => [
                    ['text' => 'Richtig', 'correct' => true],
                    ['text' => 'Falsch', 'correct' => false],
                    ['text' => 'Nein', 'correct' => false],
                    ['text' => 'Vielleicht', 'correct' => false],
                ],
                'correct_answer' => 'Richtig',
            ],
            'free' => [
                'prompt' => 'Erkläre Testfakt.',
                'correct_answer' => $unit['content'] ?? 'Testinhalt',
            ],
        ];
    }

    public function gradeFreetextAnswer(array $unit, string $question, string $answer): array
    {
        return ['result' => 'correct', 'feedback' => 'Korrekt.'];
    }

    public function prioritiseReview(array $unitHistory): array
    {
        return array_map(fn (array $u): array => [
            'knowledge_unit_id' => $u['id'],
            'priority' => 50,
            'due_at' => Carbon::now()->addDay()->toIso8601String(),
        ], $unitHistory);
    }
}
