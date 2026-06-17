<?php

namespace App\Contracts;

use App\Exceptions\LLMException;
use App\Services\FakeLLMService;

/**
 * Abstraction over the local LLM (Ollama). Every method returns decoded,
 * schema-validated arrays and throws {@see LLMException} on any transport or
 * parsing failure, so callers (jobs) can map failures to status columns.
 *
 * The fake counterpart used in tests is {@see FakeLLMService}.
 */
interface LLMServiceInterface
{
    /**
     * Extract knowledge units from a document's raw markdown.
     *
     * @return array{units: array<int, array<string, mixed>>}
     *
     * @throws LLMException
     */
    public function extract(string $markdown): array;

    /**
     * Suggest how a staged upload maps onto existing projects (or a new one).
     *
     * @param  array<int, array{id: int, name: string}>  $existingProjects
     * @return array{suggestions: array<int, array<string, mixed>>}
     *
     * @throws LLMException
     */
    public function classifyUpload(string $markdown, array $existingProjects): array;

    /**
     * Generate one multiple-choice and one free-text question for a unit.
     *
     * @param  array<string, mixed>  $unit
     * @return array{mc?: array<string, mixed>, free?: array<string, mixed>}
     *
     * @throws LLMException
     */
    public function generateQuestions(array $unit): array;

    /**
     * Grade a free-text answer against a unit.
     *
     * @param  array<string, mixed>  $unit
     * @return array{result: string, feedback: string}
     *
     * @throws LLMException
     */
    public function gradeFreetextAnswer(array $unit, string $question, string $answer): array;

    /**
     * Re-rank due review states by priority.
     *
     * @param  array<int, array<string, mixed>>  $unitHistory
     * @return array<int, array{knowledge_unit_id: int, priority: int, due_at: string}>
     *
     * @throws LLMException
     */
    public function prioritiseReview(array $unitHistory): array;
}
