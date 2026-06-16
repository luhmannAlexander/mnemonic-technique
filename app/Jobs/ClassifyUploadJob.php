<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Classifies a staged upload against existing projects via the LLM.
 *
 * Milestone 1 dispatches this on global upload but the handler is a no-op:
 * automatic classification needs the AI pipeline (Milestone 2, ImplementationPlan
 * §2.4). Until then the global-upload UI uses the documented manual fallback
 * (AppFlow §2.5 — "Automatische Zuordnung gerade nicht verfügbar").
 */
class ClassifyUploadJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public int $stagingId) {}

    public function handle(): void
    {
        // Implemented in Milestone 2 (ImplementationPlan §2.4).
    }
}
