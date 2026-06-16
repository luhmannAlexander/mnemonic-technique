<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\Project;
use App\Models\UploadStaging;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Promotes a confirmed upload staging into a real Project document and kicks off
 * extraction (BackendSchema §2.5 promotion flow, ImplementationPlan §2.4).
 *
 * No AI is involved, so this is functional in Milestone 1: it backs the manual
 * project-assignment path of the global upload (AppFlow §2.5 fallback).
 */
class PromoteUploadStagingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $stagingId) {}

    public function handle(): void
    {
        $staging = UploadStaging::find($this->stagingId);

        if ($staging === null) {
            return;
        }

        $document = DB::transaction(function () use ($staging): Document {
            $project = $this->resolveProject($staging);

            $document = Document::create([
                'project_id' => $project->id,
                'user_id' => $project->user_id,
                'filename' => $staging->filename,
                'raw_markdown' => $staging->raw_markdown,
                'file_size_bytes' => $staging->file_size_bytes,
                'status' => 'pending',
            ]);

            $staging->delete();

            return $document;
        });

        ExtractKnowledgeJob::dispatch($document->id);
    }

    /** Use the assigned existing project, or create a new one from the staged name. */
    private function resolveProject(UploadStaging $staging): Project
    {
        if ($staging->assigned_project_id !== null) {
            return Project::where('user_id', $staging->user_id)
                ->findOrFail($staging->assigned_project_id);
        }

        return Project::create([
            'user_id' => $staging->user_id,
            'name' => $staging->assigned_project_name ?: $staging->filename,
        ]);
    }
}
