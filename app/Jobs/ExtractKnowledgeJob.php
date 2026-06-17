<?php

namespace App\Jobs;

use App\Contracts\LLMServiceInterface;
use App\Exceptions\LLMException;
use App\Models\Document;
use App\Models\KnowledgeUnit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Extracts knowledge units from a document's raw markdown via the LLM and
 * persists them as `draft` units for the user to review (ImplementationPlan §2.3).
 *
 * Status machine: pending → processing → done | error. Capped at two attempts;
 * on LLM failure the document is flagged `error` and the exception is re-thrown
 * so Horizon records the failed attempt.
 */
class ExtractKnowledgeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $documentId) {}

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(5);
    }

    public function handle(LLMServiceInterface $llm): void
    {
        $document = Document::find($this->documentId);

        if ($document === null) {
            return;
        }

        if ($document->extraction_attempts >= 2) {
            $document->update(['status' => 'error', 'error_detail' => 'Maximale Anzahl Versuche erreicht.']);

            return;
        }

        $document->increment('extraction_attempts');
        $document->update(['status' => 'processing']);

        try {
            $result = $llm->extract($document->raw_markdown);
            $this->persistUnits($document, $result['units']);
            $document->update([
                'status' => 'done',
                'error_detail' => null,
                'extracted_unit_count' => count($result['units']),
            ]);
        } catch (LLMException $e) {
            $document->update(['status' => 'error', 'error_detail' => $e->getMessage()]);

            throw $e; // Let Horizon mark the job attempt as failed.
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $units
     */
    private function persistUnits(Document $document, array $units): void
    {
        foreach ($units as $unit) {
            KnowledgeUnit::create([
                'document_id' => $document->id,
                'project_id' => $document->project_id,
                'user_id' => $document->user_id,
                'type' => $unit['type'],
                'title' => $unit['title'],
                'content' => $unit['content'],
                'source_ref' => $unit['source_ref'] ?? null,
                'topic_tag' => $unit['topic_tag'] ?? null,
                'unit_status' => 'draft',
                'technique' => $unit['technique'] ?? 'spaced',
                'technique_material' => $unit['technique_material'] ?? null,
            ]);
        }
    }
}
