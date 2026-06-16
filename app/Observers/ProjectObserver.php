<?php

namespace App\Observers;

use App\Models\Document;
use App\Models\Project;

class ProjectObserver
{
    /**
     * Cascade soft-delete to documents (which cascade to knowledge_units).
     * BackendSchema §8.1 — uses whereNull('deleted_at') so already-deleted
     * children keep their original timestamp (preserves restore logic).
     */
    public function deleting(Project $project): void
    {
        $project->documents()->whereNull('deleted_at')->get()
            ->each(fn (Document $document) => $document->delete());
    }

    /**
     * Restore documents that were cascaded with this project (1s grace window).
     * BackendSchema §8.2.
     */
    public function restoring(Project $project): void
    {
        if ($project->deleted_at === null) {
            return;
        }

        $project->documents()
            ->onlyTrashed()
            ->where('deleted_at', '>=', $project->deleted_at->copy()->subSecond())
            ->get()
            ->each(fn (Document $document) => $document->restore());
    }
}
