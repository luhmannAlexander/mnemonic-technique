<?php

namespace App\Jobs;

use App\Contracts\LLMServiceInterface;
use App\Exceptions\LLMException;
use App\Models\Project;
use App\Models\UploadStaging;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Classifies a staged upload against the user's existing projects via the LLM
 * (ImplementationPlan §2.4). The suggestion blob is stored on the staging row
 * for the user to confirm in the global-upload UI.
 *
 * Status: pending → classifying → awaiting_confirmation | failed. On failure the
 * UI falls back to manual project assignment (AppFlow §2.5).
 */
class ClassifyUploadJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public int $stagingId) {}

    public function handle(LLMServiceInterface $llm): void
    {
        $staging = UploadStaging::find($this->stagingId);

        if ($staging === null || $staging->confirmed_at !== null) {
            return;
        }

        $staging->update(['classification_status' => 'classifying']);

        $existingProjects = Project::where('user_id', $staging->user_id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Project $p): array => ['id' => $p->id, 'name' => $p->name])
            ->all();

        try {
            $result = $llm->classifyUpload($staging->raw_markdown, $existingProjects);

            $staging->update([
                'classification_status' => 'awaiting_confirmation',
                'classification_error' => null,
                'ai_suggestion_payload' => $result,
            ]);
        } catch (LLMException $e) {
            $staging->update([
                'classification_status' => 'failed',
                'classification_error' => $e->getMessage(),
            ]);

            throw $e; // Let Horizon record the failed attempt.
        }
    }
}
