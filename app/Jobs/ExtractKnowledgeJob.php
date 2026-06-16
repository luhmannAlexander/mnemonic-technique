<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Extracts knowledge units from a document's raw markdown via the LLM.
 *
 * Milestone 1 dispatches this on upload/retry but the handler is a no-op:
 * documents stay in `pending` until the AI pipeline lands in Milestone 2
 * (ImplementationPlan §2.3), which fills handle() with the extraction logic.
 */
class ExtractKnowledgeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $documentId) {}

    public function handle(): void
    {
        // Implemented in Milestone 2 (ImplementationPlan §2.3).
    }
}
